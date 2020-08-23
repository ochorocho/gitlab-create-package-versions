<?php

namespace Ochorocho;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;
use Gitlab\Client;
use Gitlab\Api\RepositoryFiles;
use Gitlab\Exception\RuntimeException;
use Gitlab\ResultPager;
use Http\Discovery\Exception\NotFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class GitlabComposerPackagesCommand extends Command {

    /**
     * API Connection
     *
     * @var object $client
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

        echo "######";

        return Command::SUCCESS;
    }

    function connectToGitlab() {
        $this->client = new Client();
        $this->client->setUrl($this->config['GITLAB_URL']);
        $this->client->authenticate($this->config['GITLAB_TOKEN'], Client::AUTH_HTTP_TOKEN);
    }

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

        return $this->filterEmptyTags($this->excludeFilter($composerProjects));
    }

    private function excludeFilter($projects) {
        $regex = array_key_exists('EXLUDE_REGEX', $this->config) ? $this->config['EXLUDE_REGEX'] : '/.*/';
        $filteredProjects = [];

        foreach ($projects as $project) {
            if(!empty($project['path_with_namespace']) && preg_match($regex, $project['path_with_namespace'])) {
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

    private function filterEmptyTags($projects) {
        $this->output->writeln('');
        $projectsWithTags = [];

        foreach ($projects as $project) {
            $tags = $this->getTags($project);

            if(!empty($tags)) {
                $this->output->writeln('<info>' . $project['path_with_namespace'] . '</info>');
                $this->output->writeln(implode(', ', array_column($tags, 'name', 'id')));

                $projectsWithTags[] = $project;
            }
        }
    }

    private function getTags($project) {
        $tags = $this->client->tags()->all($project['id']);

        return $tags;
    }
}
