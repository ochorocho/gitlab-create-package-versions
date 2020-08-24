<?php

namespace Ochorocho;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;
use Gitlab\Client;
use Gitlab\Api\RepositoryFiles;
use Gitlab\Exception\RuntimeException;
use Gitlab\ResultPager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class GitlabComposerPackagesCommand extends Command {

    /**
     * API Connection
     *
     * @var Client $client
     */
    protected $client;

    /**
     * API Files
     *
     * @var RepositoryFiles $files
     */
    protected $files;

    /**
     * Env config
     *
     * @var Client $config
     */
    protected $config;

    /**
     * OutputInterface
     *
     * @var OutputInterface $output
     */
    protected $output;

    /**
     * InputInterface
     *
     * @var InputInterface $input
     */
    protected $input;

    protected static $defaultName = 'gitlab:create-composer-packages';

    protected function configure()
    {

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->input = $input;

        $this->loadConfig();
        $this->connectToGitlab();
        $this->files = new RepositoryFiles($this->client);
        $projects = $this->getComposerProjects();

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Create versions for the listed projects above [y|n] ? ', false, '/^(y|j)/i');

        if (!$helper->ask($input, $output, $question)) {
            return Command::SUCCESS;
        }

        foreach($projects as $project) {
            $this->createVersion($project);
        }

        return Command::SUCCESS;
    }

    function connectToGitlab() {
        $this->client = new Client();
        $this->client->setUrl($this->config['GITLAB_URL']);
        $this->client->authenticate($this->config['GITLAB_TOKEN'], Client::AUTH_HTTP_TOKEN);
    }

    /**
     * Get all projects containing composer.json
     *
     * @return array
     * @throws \Http\Client\Exception
     */
    private function getComposerProjects() {
        $pager = new ResultPager($this->client);
        $projects = $pager->fetchAll($this->client->projects(), 'all');

        $composerProjects = [];
        $this->output->writeln('<info>Get all composer based projects</info>');

        $progressBar = new ProgressBar($this->output, count($projects));
        $progressBar->start();

        foreach ($projects as $project) {
            $progressBar->advance();
            try {
                $this->files->getFile($project['id'], 'composer.json', 'master');
                $composerProjects[] = $project;
            } catch (RuntimeException $exception) {
                // Catch Exception
            }
        }
        $progressBar->finish();
        $this->output->writeln('');

        return $this->filterEmptyTags($this->excludeFilter($composerProjects));
    }

    /**
     * Respect exclude regex
     *
     * @param array $projects
     * @return array
     */
    private function excludeFilter($projects) {
        $regex = array_key_exists('INCLUDE_ONLY_REGEX', $this->config) ? $this->config['INCLUDE_ONLY_REGEX'] : '/.*/';
        $filteredProjects = [];

        foreach ($projects as $project) {
            if(@preg_match($regex, $project['path_with_namespace'], $matches) === false) {
                $this->output->writeln('<error>Invalid Regular Expression: ' . $this->config['INCLUDE_ONLY_REGEX'] . '</error>');
                exit();
            }

            if(!empty($project['path_with_namespace']) && !empty($matches)) {
                $filteredProjects[] = $project;
            }
        }

        return $filteredProjects;
    }

    private function loadConfig() {
        try {
            $config = Dotenv::createImmutable(getcwd());
            $this->config = $config->load();
        } catch (InvalidPathException $exception) {
            $this->output->writeln('<error>Could not find .env file in current folder</error>');
            exit();
        }
    }

    /**
     * Get rid of all projects without any tags and print info about project and its tags
     *
     * @param $projects
     * @return array
     */
    private function filterEmptyTags($projects) {
        $this->output->writeln('');
        $projectsWithTags = [];

        foreach ($projects as $project) {
            $tags = $this->getTags($project);

            if(!empty($tags)) {
                $this->output->writeln('<info>' . $project['path_with_namespace'] . '</info>');
                $this->output->writeln(implode(', ', array_column($tags, 'name', 'id')));
                $project['tags'] = $tags;
                $projectsWithTags[] = $project;
            }
        }

        return $projectsWithTags;
    }

    /**
     * Get all tags of a project
     *
     * @param $project
     * @return mixed
     */
    private function getTags($project) {
        return $this->client->tags()->all($project['id']);
    }

    private function createVersion($project) {
        $this->ensurePackagesEnabled($project);
        $client = $this->client->getHttpClient();

        foreach (array_column($project['tags'], 'name', 'id') as $tag) {
            $request = $client->post('api/v4/projects/' . $project['id'] . '/packages/composer?tag=' . $tag);

            if($request->getStatusCode() === 201) {
                $this->output->writeln('Created Package' . $project['path_with_namespace'] . ':' . $tag);
            } else {
                $this->output->writeln($project['path_with_namespace'] .' ' . $request->getStatusCode());
            }
        }
    }

    private function ensurePackagesEnabled($project) : void {
        if($project['packages_enabled'] !== true) {
            $this->client->projects()->update($project['id'], ['packages_enabled' => true]);
        }
    }
}
