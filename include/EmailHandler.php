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
        $result = $this->mgClient->sendMessage("$this->domain", array('from' => 'CeliTax Team <no-reply@celitax.ca>',
            'to' => "CeliTax user <$email>",
            'subject' => 'Password Reset for CeliTax',
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
        $result = $this->mgClient->sendMessage("$this->domain", array('from' => 'CeliTax Team <no-reply@celitax.ca>',
            'to' => "CeliTax user <$email>",
            'subject' => 'Receipts Link for CeliTax',
            'text' => "Hi $firstname $lastname,\n\nThe link to view and download the receipts that you have requested, please click here:\n$linkURL"));
        return $result;
    }
    
    /**
     * Send a email with the link to view the year summary
     * @return result of the email sender
     */
    public function sendYearSummaryLink($email, $linkURL, $firstname, $lastname)
    {
        # Issue the call to the client.
        $result = $this->mgClient->sendMessage("$this->domain", array('from' => 'CeliTax Team <no-reply@celitax.ca>',
            'to' => "CeliTax user <$email>",
            'subject' => 'Year Summary for CeliTax',
            'text' => "Hi $firstname $lastname,\n\nThe link to view and print the year-end summary that you have requested, please click here:\n$linkURL"));
        return $result;
    }
    
    public function notifyAppFeedback($name, $email, $comments)
    {
        $admin_email = "info@celitax.ca";
        $admin_email2 = "leon.chen@celitax.ca";
        
        $result = $this->mgClient->sendMessage("$this->domain",
                  array('from'    => "$email",
                        'to'      => "Admin <$admin_email>",
                        'subject' => "Celitax App Feedback from $name",
                        'text'    => "$comments"));
        
        $result2 = $this->mgClient->sendMessage("$this->domain",
                  array('from'    => "$email",
                        'to'      => "Admin <$admin_email2>",
                        'subject' => "Celitax App Feedback from $name",
                        'text'    => "$comments"));
        
        return $result;
    }
    
    public function sendWelcomeMail($email) {
        $welcome_page = file_get_contents('EmailHTMLs/WelcomeEmail.html', FILE_USE_INCLUDE_PATH);
        
        # Issue the call to the client.
        $result = $this->mgClient->sendMessage("$this->domain",
                  array('from'    => 'CeliTax Team <no-reply@celitax.ca>',
                        'to'      => "New CeliTax user <$email>",
                        'subject' => 'Welcome to Celitax!',
                        'html'    => $welcome_page));
        return $result;
    }
}

?>
