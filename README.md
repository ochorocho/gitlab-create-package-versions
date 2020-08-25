# Create composer packages for your gitlab instance

## Setup

```
git clone git@github.com:ochorocho/gitlab-create-package-versions.git
composer install
```

Create `.env` file, add credentials and run `./gitlab-create-versions`

Example `.env` file

```
GITLAB_URL=https://domain.org/
GITLAB_TOKEN=<Personal Access Token>

# Projects to include. path_with_namespace used to match against Defaults to /.*/ (all Projects)
# INCLUDE_ONLY_REGEX=/.*/
```

:warning: Issues

* If you have multiple forks of a projekt API responds with `Validation failed: Name is already taken by another project`
* Only tag containing a `composer.json` file will create a new version
* Branch based versions are not created, e.g. `dev-master`