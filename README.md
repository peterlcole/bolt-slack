# Bolt-Slack

Send Bolt's content related events to Slack using [incoming-webhooks](https://api.slack.com/incoming-webhooks). 

## Configuration

After installing the plugin through the [Extension Store](https://extensions.bolt.cm/view/dbeb5c20-c10f-11e5-bf25-14cdca8e654f), Bolt will create _app/config/extensions/bolt-slack.peterlcole.yml_ with the following content:
```
emoji: ~
template_path: ~
username: ~
webhook_url: ~
events:
    create:
        pages:
            channels: ['#general']
            emoji: ~
            template: ~
    update:
        pages:
            channels: ['#general']
            emoji: ~
            template: ~
    delete:
        pages:
            channels: ['#general']
            emoji: ~
            template: ~
```
**emoji** [string, optional]  
If provided, this will be used as the icon in the Slack message. It will be used for all events and contenttypes unless overridden later.

**template_path** [string, optional]  
The directory, relative to Bolt's installation path, that holds the Twig templates used to format Slack messages. This will be ignored if a **template** is not specified.

**username** [string, optional]  
If provided, this will be the name that Slack uses in the chat.

**web_hook** [string, required]  
The url that incoming-webooks provides for you. It will start with *https://hooks.slack.com/services/*

**events : \<event\>** [array, required]  
The events that should be acted on. Possible values are:
* create
* delete
* update

**events : \<event\> : \<contenttype\>** [array, required]  
Only the contenttypes you specify will be acted on.

**events : \<event\> : \<contenttype\> : channels** [array, required]  
An array of message receivers. Can be channels (e.g., '#general') or direct messages (e.g., '@intendedusername').

**events : \<event\> : \<contenttype\> : emoji** [string, optional]  
If provided, this dictates the icon used in the Slack message. It will override the global **emoji** setting.

**events : \<event\> : \<contenttype\> : template** [array, required]  
The name of a Twig file in the **template_path** directory that will be used for these messages. This will be ignored if **template_path** is not specified.

### Configuration examples
```
username: Super Slack Man
webhook_url: https://hooks.slack.com/services/ABC/DEF/123
template_path: ~
events:
    create:
        pages:
            channels: ['#general']
    delete:
        pages:
            channels: ['#general']
```
```
webhook_url: https://hooks.slack.com/services/ABC/DEF/123
events:
    create:
        pages:
            channels: ['#general']
        articles:
            channels: ['#bloggers', '@editorusername']
```
```
emoji: ':ghost:'
template_path: files/slack-templates
webhook_url: https://hooks.slack.com/services/ABC/DEF/123
events:
    create:
        pages:
            channels: ['#general']
            template: page-create.twig
        articles:
            channels: ['#bloggers', '@editorusername']
            emoji: ':phone:'
            template: article-create.twig
    update:
        pages:
            channels: ['#updatersclub']
            template: page-update.twig
    delete:
        pages:
            channels: ['#general']
            template: page-delete.twig
        articles:
            channels: ['#general']
            template: article-delete.twig
```
