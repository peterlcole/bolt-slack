<?php

namespace Bolt\Extension\Peterlcole\Slack;

use Bolt\Application;
use Bolt\BaseExtension;

class Extension extends BaseExtension
{
    protected $extensionConfig;

    public function getName()
    {
        return "Slack";
    }

    public function initialize()
    {
        $this->app['dispatcher']->addListener(\Bolt\Events\StorageEvents::POST_SAVE,  array($this, 'saveContent'));
        $this->app['dispatcher']->addListener(\Bolt\Events\StorageEvents::PRE_DELETE, array($this, 'deleteContent'));
    }


    public function deleteContent(\Bolt\Events\StorageEvent $event)
    {
        if (false === $this->sanityCheck()) {
            return;   
        }

        $contentType = $event->getArguments();
        $contentType = $contentType['contenttype'];
        $content     = $event->getContent();
        $hook        = 'delete';
        $hookFound   = in_array('delete', $this->extensionConfig['content'][$contentType]['events']);

        if ($hookFound) {

            $currentUser = $this->app['users']->getCurrentUser();
            $msg         = sprintf('%s deleted %s from %s.', 
                ucfirst($currentUser['displayname']), 
                $content['slug'],
                $contentType
            );

            $this->send($msg, $contentType);
        }
    }


    protected function sanityCheck()
    {
        $sane = true;
        $this->extensionConfig = $this->app['config']->get('general/slack', null);

        // Return early for root config
        if (null === $this->extensionConfig) {
            $msg  = 'Missing option in config.yml: slack';
            $this->app['logger.system']->addError($msg, array('event' => 'content'));
            return false;
        }

        if (false === isset($this->extensionConfig['webhook_url'])) {
            $msg  = 'Missing option in config.yml: webhook_url';
            $sane = false;
        }

        if (false === isset($this->extensionConfig['content'])) {
            $msg  = 'Missing option in config.yml: content';
            $sane = false;
        }

        foreach ($this->extensionConfig['content'] as $content) {
            if (false === isset($content['channels'])) {
                $msg  = 'Missing option in config.yml: channels';
                $sane = false;
            }   

            if (false === isset($content['events'])) {
                $msg  = 'Missing option in config.yml: events';
                $sane = false;
            }            
        }

        if (false === $sane) {
            $this->app['logger.system']->addError($msg, array('event' => 'content'));
        }

        return $sane;
    }


    public function saveContent(\Bolt\Events\StorageEvent $event)
    {
        if (false === $this->sanityCheck()) {
            return;   
        }

        $content     = $event->getContent();
        $hook        = $event->isCreate() ? 'create' : 'update';
        $hookFound   = in_array($hook, $this->extensionConfig['content'][$event->getContentType()]['events']);

        $actions = array(
            'create' => 'created',
            'update' => 'updated',
        );

        if ($hookFound) {

            $currentUser = $this->app['users']->getCurrentUser();
            $contentType = strtolower($content->contenttype['singular_name']);
            $link        = $this->app['resources']->getUrl('rooturl') . strtolower($event->getContentType()) . '/' . $event->getId();
            $msg         = sprintf('%s %s %s <%s|%s>', 
                ucfirst($currentUser['displayname']), 
                $actions[$hook],
                $contentType,
                $link,
                $content->getTitle()
            );

            $this->send($msg, $event->getContentType());
        }
    }


    protected function send($msg, $contentType) 
    {
        $channels = $this->app['config']->get('general/slack/content/' . $contentType . '/channels', null);
        $channels = is_array($channels) ? $channels : array($channels);

        foreach ($channels as $channel) {

            $data = array(
                'channel'  => $channel,
                'text'     => $msg,
            );

            if ($this->extensionConfig['username']) {
                $data['username'] = $this->extensionConfig['username'];
            }

            $data = array(
                'body' => json_encode($data),
            );

            $request = $this->app['guzzle.client']->post($this->extensionConfig['webhook_url'], $data);
        }
    }
}
