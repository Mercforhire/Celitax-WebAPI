<?php

# Include the Autoloader (see "Libraries" for install instructions)
require '../vendor/autoload.php';
use Mailgun\Mailgun;

/**
 * Handling sending emails
 *
 * @author Leon Chen
 */
class EmailHandler
{
    # Instantiate the client.

    private $mgClient;
    private $domain;

    function __construct()
    {
        $this->mgClient = new Mailgun('key-01zs3m0dem69g1gxdmikjyeevl45ms81');
        $this->domain = 'sandbox78922.mailgun.org';
    }

    /**
     * Send a email with the link to reset the password
     * @return result of the email sender
     */
    public function sendPasswordResetEmailWithToken($email, $tokenid, $firstname, $lastname)
    {
        # Issue the call to the client.
        $result = $this->mgClient->sendMessage("$this->domain", array('from' => 'Celitax Team <no-reply@celitax.ca>',
            'to' => "Celitax user <$email>",
            'subject' => 'Password Reset for Celitax',
            'text' => "Hi $firstname $lastname,\n\nDon't worry about forgetting your password. "
            . "It happens to the best of us. Please click the link below and we will get you set up with a "
            . "new password!\n\n"
            . "Click here to reset it:\nhttp://celitax.ca/reset.php?tokenid=$tokenid"));
        return $result;
    }

    /**
     * Send a email with the link to download the selected receipts
     * @return result of the email sender
     */
    public function sendReceiptDownloadLink($email, $linkURL, $firstname, $lastname)
    {
        # Issue the call to the client.
        $result = $this->mgClient->sendMessage("$this->domain", array('from' => 'Celitax Team <no-reply@celitax.ca>',
            'to' => "Celitax user <$email>",
            'subject' => 'Receipts Link for Celitax',
            'text' => "Hi $firstname $lastname,\n\nHere is the link to view and download the receipts you have selected.\n\n"
            . "$linkURL"));
        return $result;
    }
}

?>
