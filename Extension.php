<?php

namespace Bolt\Extension\Peterlcole\Slack;

use Bolt\Application;
use Bolt\BaseExtension;
use Bolt\Content;

class Extension extends BaseExtension
{
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
        $contentType = $this->content->contenttype['slug'];
        $hook        = 'delete';
        $config      = $this->config['events'][$hook][$contentType];

        if (false === $this->sanityCheck() OR null === $config) {
            return;
        }

        if (isset($this->config['template_path']) AND isset($config['template'])) {
            $config['templateDir'] = $this->config['template_dir'];
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
        $this->content = $this->app['storage']->getContent($contentType . '/' . $contentId);

        // If the content is not published then $content will be false. Consequently, the extension will explode if you
        // treat it like an array so it is best if we just avoid doing that. The callbacks will test $content and exit
        // early so this is safe.
        if (false !== $this->content) {
            $this->content->owner = $this->app['users']->getUser($this->content->getValues()['ownerid']);
        }
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
     * Make sure all requirements are met.
     *
     * Note: $this->loadContent() must be called before this method.
     *
     * @return bool
     */
    protected function sanityCheck()
    {
        $isSane = true;

        // Return early for root config
        if (empty($this->config)) {
            $msg  = 'Missing bolt-slack configuration.';
            $this->app['logger.system']->addError($msg, array('event' => 'content'));
            return false;
        }

        if (false === $this->content) {
            $msg = 'Bolt-slack could not find the content. This is likely because the status is not published.';
            $isSane = false;
        }

        if (false === isset($this->config['webhook_url']) OR null === $this->config['webhook_url']) {
            $msg    = 'Missing or invalid bolt-slack configuration: webhook_url';
            $isSane = false;
        }

        if (false === isset($this->config['events'])) {
            $msg    = 'Missing bolt-slack configuration: events';
            $isSane = false;
        }

        foreach ($this->config['events'] as $contentTypes) {

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
        $hook        = $event->isCreate() ? 'create' : 'update';
        $config      = $this->config['events'][$hook][$event->getContentType()];

        $this->loadContent($event->getContentType(), $event->getId());

        if (false === $this->sanityCheck() OR null === $config ) {
            return;
        }

        if (isset($this->config['template_path']) AND null !== $this->config['template_path'] AND
            isset($config['template']) AND null !== $this->config['template']) {

            $config['templateDir'] = $this->app['resources']->getPath('rootpath') . $this->config['template_path'];

        } else {
            $config['templateDir'] = __DIR__ . '/templates';
            $config['template']    = $hook . '.twig';
        }

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

            if (isset($this->config['emoji']) AND null !== $this->config['emoji']) {
                $payload['icon_emoji'] = $this->config['emoji'];
            }

            if (isset($config['emoji']) AND null !== $config['emoji']) {
                $payload['icon_emoji'] = $config['emoji'];
            }

            if (isset($this->config['username']) AND null !== $this->config['username']) {
                $payload['username'] = $this->config['username'];
            }

            $payload = array(
                'body'        => json_encode($payload),
            );

            $request = $this->app['guzzle.client']->post($this->config['webhook_url'], $payload);
        }
    }
 }