<?php namespace Ndcisiv\DisposableEmailBlocker\Components;

use Cms\Classes\CmsException;
use Cms\Classes\ComponentBase;
use Ndcisiv\DisposableEmailBlocker\Models\DisposableSettings;
use Event;
use Flash;
use Redirect;
use Mail;

class VerifyEmail extends ComponentBase
{

    /**
     * Component Detail information
     * @return array
     */
    public function componentDetails()
    {
        return [
            'name' => 'VerifyEmail Component',
            'description' => 'Processes an email address to see if it is from a disposable domain.'
        ];
    }

    /**
     * Method checks email address to see if it is from a disposable/temporary domain using
     * block-disposable-email.com's API access
     * http://www.block-disposable-email.com/cms/
     *
     * @param $email
     * @return bool
     * @throws CmsException
     */
    public function checkAddress($email)
    {
        try {
            // Verify an API key is setup
            $settings = DisposableSettings::instance();
            if (!$settings->api_key) {
                throw new CmsException('API key is not configured.');
            }

            // Check that the email is properly formatted
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // Process the email address and strip out the domain
                $email_array = explode('@', $email);
                $domain = array_pop($email_array);

                // Prepare the request and get results
                $request = 'http://check.block-disposable-email.com/easyapi/json/' . $settings->api_key . '/' . $domain;
                $response = file_get_contents($request);
                $dea = json_decode($response);

                // Test the results
                if ($dea->request_status == 'success') {
                    if ($dea->domain_status == 'block') {
                        //Access Denied
                        return false;
                    } else {
                        // Access Granted
                        return true;
                    }
                } elseif ($dea->request_status == 'fail_key') {
                    // The API key is entered, but is not valid.  Allow the registration but notify the site email
                    $msg = array(
                        'type' => 'fail_key',
                        'content' => 'Something is wrong with your api key.  Please double-check or request a new one.'
                    );
                    $this->sendNotificationEmail($msg);
                    return true;
                } elseif ($dea->request_status == 'fail_server') {
                    // There is a problem contacting the verification server.  Allow the registration but notify the site email
                    $msg = array(
                        'type' => 'fail_server',
                        'content' => 'The server could not connect to the database or had some other problems.'
                    );
                    $this->sendNotificationEmail($msg);
                    return true;
                } elseif ($dea->request_status == 'fail_input_domain') {
                    // The domain is formatted incorrectly, or does not exist
                    throw new CmsException('The email domain is in the wrong format or does not exist.  Please try again.');
                } elseif ($dea->request_status == 'fail_key_low_credits') {
                    // You are out of credits.  Allow the registration but notify the site email
                    $msg = array(
                        'type' => 'fail_key_low_credits',
                        'content' => 'You used up your credits.  The current and any additional request will be answered with ok without really checking the domain.  Consider buying additional credits.'
                    );
                    $this->sendNotificationEmail($msg);
                    return true;
                } else {
                    // something else went wrong with the address (maybe a malformed domain)
                    throw new CmsException('There is a problem with the Email Address you provided.  Please try again.');
                }
            } else {
                throw new CmsException('The Email Address you provided is not properly formatted.  Please try again.');
            }
        } catch (CmsException $e) {
            Flash::error($e->getMessage());
            Redirect::back();
        }

    }

    /**
     * Component init method
     * Listens for event to check email at registration for bad email addresses
     */
    public function init()
    {
        // Only attach if the plugin has been enabled
        $settings = DisposableSettings::instance();

        if ($settings->plugin_enabled) {
            // Listen for ajax event and process email prior to it running
            Event::listen('cms.component.beforeRunAjaxHandler', function ($this, $handler) {

                try {
                    // Only continue for onRegister handler
                    if ($handler != 'onRegister') {
                        return;
                    }

                    // Grab email from registration form and check it
                    $emailtoverify = post('email');
                    $verifier = new VerifyEmail();
                    $goodemail = $verifier->checkAddress($emailtoverify);

                    // If the email is legit, continue operation
                    if ($goodemail) {
                        return;
                    }

                    // The email came back bad, throw exception
                    throw new CmsException('This site does not allow temporary or disposable emails for registration.  Please use a valid email account.');
                } catch (CmsException $e) {
                    Flash::error($e->getMessage());
                    return Redirect::back();
                }

            });
        }

    }

    /**
     * Send out notification emails if requested to do so
     * @param $msg
     */
    protected function sendNotificationEmail($msg)
    {
        $settings = DisposableSettings::instance();

        if ($settings->receive_notification_emails) {
            Mail::send('ndcisiv.disposableemailblocker::mail.inform', $msg, function ($message) use ($settings) {
                $message->to($settings->notification_email, $settings->notification_email);
            });
        }

    }

}