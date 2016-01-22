# Slack

Send Bolt's content related events to Slack using [incoming-webhooks](https://api.slack.com/incoming-webhooks). 

## Configuration

To your *config.yml*, add the following options:
```
slack:
    web_hook:
    username:
    content:
        <contenttype>:
            channels: ['#general']
            events:
                - create
                - delete
                - update
```

**web_hook** [string, required]  
The url that incoming-webooks provides for you. It will start with *https://hooks.slack.com/services/*

**username** [string, optional]  
If provided, this will be the name that Slack uses in the chat.

**content** [array, required]  
Only storage events related to the specified content will be sent to Slack

**<contenttype>** [array, required]  
A type of content to act on. E.g., `pages:`. Must be lower case.

**channels** [array, required]  
The same event can be sent to multiple channels (anything starting with a *#*) as well as direct messages (user names prefixed with *@*).

**events** [array, required]  
The events that should be sent to Slack. Possible values are:
* create
* delete
* update

### Configuration examples
```
slack:
    web_hook: https://hooks.slack.com/services/ABC/DEF/123
    username: Super Bolt Man
    content:
        pages:
            channels: ['#general']
            events:
                - create
                - update
```
```
slack:
    web_hook: https://hooks.slack.com/services/ABC/DEF/123
    content:
        pages:
            channels: ['#general']
            events:
                - create
                - update
        entries:
            channels: ['#editors', '@myslackusername']
            events:
                - delete
```