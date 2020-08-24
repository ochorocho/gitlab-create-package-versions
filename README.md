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
