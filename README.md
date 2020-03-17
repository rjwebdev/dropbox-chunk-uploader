# Dropbox Proof-of-concept chunk file upload
This small project contains a Symfony command that can be used to upload large files in chunk to Dropbox

## Project setup
* Clone the git repository
* Run `composer install`
* Create an app in the Dropbox App Console if you don't have one already
* Copy the `.env` file to a `.env.local` file and fill in the `DROPBOX_ACCESS_TOKEN` token with an access token that can be found in the app in the Dropbox App Console
* Change the `dropbox_base_path` parameter in `config/services/yaml` file to configure where the files should be stored in Dropbox
* Run the command `php bin/console app:upload-file <path>` with the full path to the file you want to upload
* Wait a while until the command is finished
* You're file should be visible in 