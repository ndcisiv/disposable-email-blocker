<?php namespace Ndcisiv\DisposableEmailBlocker;

use System\Classes\PluginBase;

/**
 * DisposableEmailBlocker Plugin Information File
 */
class Plugin extends PluginBase
{

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'Disposable Email Blocker',
            'description' => 'Blocks users from registering accounts using disposable email addresses.',
            'author'      => 'Ndcisiv',
            'icon'        => 'icon-envelope-square'
        ];
    }

    /**
     * Register our settings for the back end
     * @return array
     */
    public function registerSettings()
    {
        return [
            'settings' => [
                'label'       => 'Disposable Email Blocker',
                'icon'        => 'icon-envelope-square',
                'description' => 'Setup configuration for checking emails against a disposable domain list.',
                'class'       => 'Ndcisiv\DisposableEmailBlocker\Models\DisposableSettings',
                'order'       => 100
            ]
        ];
    }

    /**
     * Register emailProtect component
     * @return array
     */
    public function registerComponents()
    {
        return [
            '\Ndcisiv\DisposableEmailBlocker\Components\VerifyEmail' => 'verifyEmail'
        ];
    }

    /**
     * Register email templates
     * @return array
     */
    public function registerMailTemplates()
    {
        return [
            'ndcisiv.disposableemailblocker::mail.inform' => 'Informational notifications to email.',
        ];
    }


}
