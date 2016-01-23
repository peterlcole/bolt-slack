<?php

namespace Bolt\Extension\Peterlcole\Slack;

use Bolt\Application;
use Bolt\BaseExtension;
use Bolt\Content;

class Extension extends BaseExtension
{
    protected $extensionConfig;

    protected $content;

    public function getName()
    {
        return "Slack";
    }

    public function initialize()
    {
        $this->app['dispatcher']->addListener(\Bolt\Events\StorageEvents::POST_SAVE,  array($this, 'saveContent'));
        $this->app['dispatcher']->addListener(\Bolt\Events\StorageEvents::PRE_DELETE, array($this, 'preDelete'));
        $this->app['dispatcher']->addListener(\Bolt\Events\StorageEvents::POST_DELETE, array($this, 'deleteContent'));
    }

    /**
     * Retrieve and store content information to be used in the POST_DELETE callback
     */
    public function preDelete($event)
    {
        $this->loadContent($event->getArguments()['contenttype'], $event->getSubject()['id']);
    }

    public function deleteContent(\Bolt\Events\StorageEvent $event)
    {

        if (false === $this->sanityCheck()) {
            return;   
        }

        file_put_contents('/tmp/storage.log', var_export($event->getContent(), true));

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


    protected function loadContent($contentType, $contentId)
    {
        $this->content              = $this->app['storage']->getContent($contentType . '/' . $contentId);
        $this->content->owner       = $this->app['users']->getUser($this->content->getValues()['ownerid']);
    }


    public function saveContent(\Bolt\Events\StorageEvent $event)
    {
        $this->extensionConfig = $this->app['config']->get('general/slack', null);
        $hook                  = $event->isCreate() ? 'create' : 'update';
        $config                = $this->extensionConfig['events'][$hook][$event->getContentType()];

        if (null === $config) {
            return;
        }

        if (isset($this->extensionConfig['template_path']) AND isset($config['template'])) {
            $config['templateDir'] = $this->extensionConfig['template_dir'];
        } else {
            $config['templateDir'] = __DIR__ . '/templates';
            $config['template']    = $hook . '.twig';
        }
file_put_contents('/tmp/storage.log', var_export($config, true));
// return;
        $this->send($event->getContentType(), $event->getId(), $config);
    }


    protected function send($contentType, $contentId, $config)
    {
        $this->app['twig.loader.filesystem']->prependPath($config['templateDir']);
        $this->loadContent($contentType, $contentId);

        $data = array(
            'content'     => $this->content,
            'currentUser' => $this->app['users']->getCurrentUser(),
            'rootUrl'     => rtrim($this->app['resources']->getUrl('rooturl'), '/'),
        );

        $channels = is_array($config['channels']) ? $config['channels'] : array($config['channels']);

        foreach ($channels as $channel) {

            $data = array(
                'channel'  => $channel,
                'text'     => $this->app['render']->render($config['template'], $data)->__toString()
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