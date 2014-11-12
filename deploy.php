<?php
/**
 * @version 1.0.0
 *
 * @see http://brandonsummers.name/blog/2012/02/10/using-bitbucket-for-automated-deployments/
 */
date_default_timezone_set('Europe/Rome');

class Deploy {

    /**
     * A callback function to call after the deploy has finished.
     *
     * @var callback
     */
    public $post_deploy;

    /**
     * The name of the file that will be used for logging deployments. Set to
     * FALSE to disable logging.
     *
     * @var string
     */
    protected $_log = '/tmp/deployments.log';

    /**
     * The timestamp format used for logging.
     *
     * @link    http://www.php.net/manual/en/function.date.php
     * @var     string
     */
    protected $_date_format = 'Y-m-d H:i:sP';

    /**
     * The name of the branch to pull from.
     *
     * @var string
     */
    protected $_branch = 'master';

    /**
     * The name of the remote to pull from.
     *
     * @var string
     */
    protected $_remote = 'origin';

    /**
     * The directory where your website and git repository are located, can be
     * a relative or absolute path
     *
     * @var string
     */
    protected $_directory;

    /**
     * Message Log Buffer (Used in email)
     *
     * @var mixed
     */
    protected $_messageBuffer;

    /**
     * Sets up defaults.
     *
     * @param  string  $directory  Directory where your website is located
     * @param  array   $data       Information about the deployment
     */
    public function __construct($directory, $options = array())
    {
        // Determine the directory path
        $this->_directory = realpath($directory).DIRECTORY_SEPARATOR;

        $available_options = array('log', 'date_format', 'branch', 'remote');

        foreach ($options as $option => $value)
        {
            if (in_array($option, $available_options))
            {
                $this->{'_'.$option} = $value;
            }
        }

        $this->log('Attempting deployment...');
    }

    /**
     * Writes a message to the log file.
     *
     * @param  string  $message  The message to write
     * @param  string  $type     The type of log message (e.g. INFO, DEBUG, ERROR, etc.)
     */
    public function log($message, $type = 'INFO')
    {
        $logMessage = date($this->_date_format).' --- '.$type.': '.$message.PHP_EOL;
        $this->_messageBuffer .= $logMessage;
        if ($this->_log)
        {
            // Set the name of the log file
            $filename = $this->_log;

            if ( ! file_exists($filename))
            {
                // Create the log file
                file_put_contents($filename, '');

                // Allow anyone to write to log files
                chmod($filename, 0666);
            }

            // Write the message into the log file
            // Format: time --- type: message
            file_put_contents($filename, $logMessage, FILE_APPEND);
        }
    }

    /**
     * Executes the necessary commands to deploy the website.
     */
    public function execute()
    {
        try
        {
            // Make sure we're in the right directory
            exec('cd '. $this->_directory . ' 2>&1', $output);
            $this->log('Changing working directory... '.implode(' ', $output));

            // Update the local repository
            exec('git pull '.$this->_remote.' '.$this->_branch . ' 2>&1', $output);
            $this->log('Pulling in changes... '.implode("\n", $output));

            if (is_callable($this->post_deploy))
            {
                call_user_func($this->post_deploy);
            }
        }
        catch (Exception $e)
        {
            $this->log($e, 'ERROR');
        }
        $headers   = array();
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-type: text/plain; charset=iso-8859-1";
        $headers[] = "From: {$_SERVER['SERVER_NAME']} <info@{$_SERVER['SERVER_NAME']}>";
        $headers[] = "Subject: Git Deployment";
        $headers[] = "X-Mailer: PHP/".phpversion();
        mail("tech@internetsm.com","Git Deployment",$this->_messageBuffer,implode("\n" , $headers));
    }

}

// This is just an example
$deploy = new Deploy(".");
$deploy->post_deploy = function() use ($deploy) {
    //do your stuff
};

$deploy->execute();