<?php

namespace Bolt\Extension\Peterlcole\Slack;

use Bolt\Application;
use Bolt\BaseExtension;
use Bolt\Content;

class Extension extends BaseExtension
{
    /**
     * @var array Extension related config
     */
    protected $extensionConfig;
    /**
     * @var \Bolt\Content
     */
    protected $content;


    public function getName()
    {
        return "Bolt-Slack";
    }


    public function initialize()
    {
        $this->app['dispatcher']->addListener(\Bolt\Events\StorageEvents::POST_SAVE,   array($this, 'saveContent'));
        $this->app['dispatcher']->addListener(\Bolt\Events\StorageEvents::PRE_DELETE,  array($this, 'preDelete'));
        $this->app['dispatcher']->addListener(\Bolt\Events\StorageEvents::POST_DELETE, array($this, 'deleteContent'));
    }


    /**
     * Implements StorageEvents::POST_DELETE
     */
    public function deleteContent(\Bolt\Events\StorageEvent $event)
    {
        $sanityCheck = $this->sanityCheck();
        $contentType = $this->content->contenttype['slug'];
        $hook        = 'delete';
        $config      = $this->extensionConfig['events'][$hook][$contentType];

        if (false === $sanityCheck OR null === $config) {
            return;
        }

        if (isset($this->extensionConfig['template_path']) AND isset($config['template'])) {
            $config['templateDir'] = $this->extensionConfig['template_dir'];
        } else {
            $config['templateDir'] = __DIR__ . '/templates';
            $config['template']    = $hook . '.twig';
        }

        $this->send($contentType, $this->content->get('id'), $config);
    }


    /**
     * A helper method to query the database for a specific piece of content.
     */
    protected function loadContent($contentType, $contentId)
    {
        $this->content              = $this->app['storage']->getContent($contentType . '/' . $contentId);
        $this->content->owner       = $this->app['users']->getUser($this->content->getValues()['ownerid']);
    }


    /**
     * The content related data available in the POST_DELETE event is limited so we use the PRE_DELETE hook to grab and
     * save it before it is deleted.
     */
    public function preDelete($event)
    {
        $this->loadContent($event->getArguments()['contenttype'], $event->getSubject()['id']);
    }


    /**
     * Make sure the extension's configuration is right.
     *
     * @return bool
     */
    protected function sanityCheck()
    {
        $isSane = true;
        $this->extensionConfig = $this->app['config']->get('general/slack', null);

        // Return early for root config
        if (null === $this->extensionConfig) {
            $msg  = 'Missing bolt-slack configuration: slack';
            $this->app['logger.system']->addError($msg, array('event' => 'content'));
            return false;
        }

        if (false === isset($this->extensionConfig['webhook_url'])) {
            $msg    = 'Missing bolt-slack configuration: webhook_url';
            $isSane = false;
        }

        if (false === isset($this->extensionConfig['events'])) {
            $msg    = 'Missing bolt-slack configuration: events';
            $isSane = false;
        }

        foreach ($this->extensionConfig['events'] as $contentTypes) {

            foreach ($contentTypes as $contentType => $config) {
                if (false === isset($config['channels'])) {
                    $msg    = 'Missing bolt-slack ' . $contentType . ' configuration: channels';
                    $isSane = false;
                }   
            }          
        }

        if (false === $isSane) {
            $this->app['logger.system']->addError($msg, array('event' => 'content'));
        }

        return $isSane;
    }


    public function saveContent(\Bolt\Events\StorageEvent $event)
    {
        $sanityCheck = $this->sanityCheck();
        $hook        = $event->isCreate() ? 'create' : 'update';
        $config      = $this->extensionConfig['events'][$hook][$event->getContentType()];

        if (false === $sanityCheck OR null === $config) {
            return;
        }

        if (isset($this->extensionConfig['template_path']) AND isset($config['template'])) {
            $config['templateDir'] = $this->app['resources']->getPath('rootpath') . $this->extensionConfig['template_path'];
        } else {
            $config['templateDir'] = __DIR__ . '/templates';
            $config['template']    = $hook . '.twig';
        }

        $this->loadContent($event->getContentType(), $event->getId());
        $this->send($event->getContentType(), $event->getId(), $config);
    }


    /**
     * A helper method to send the events to all of the Slack channels/users
     */
    protected function send($contentType, $contentId, $config)
    {
        $this->app['twig.loader.filesystem']->prependPath($config['templateDir']);

        $data = array(
            'content'     => $this->content,
            'currentUser' => $this->app['users']->getCurrentUser(),
            'rootUrl'     => rtrim($this->app['resources']->getUrl('rooturl'), '/'),
        );

        $channels = is_array($config['channels']) ? $config['channels'] : array($config['channels']);

        foreach ($channels as $channel) {

            $payload = array(
                'channel'  => $channel,
                'text'     => $this->app['render']->render($config['template'], $data)->__toString(),
            );

            if (isset($this->extensionConfig['username'])) {
                $payload['username'] = $this->extensionConfig['username'];
            }

            $payload = array(
                'body'        => json_encode($payload),
            );

            file_put_contents('/tmp/storage.' . $channel . '.log', var_export($payload, true));

            $request = $this->app['guzzle.client']->post($this->extensionConfig['webhook_url'], $payload);
        }
    }
 }