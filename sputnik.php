<?php
/**

    Sputnik SSH Version - Copying your OXID eShop done easy
    =======================================================
    
    Sputnik! is free to use, please consider a donation
    if you are using this software.
    
    LICENSE
    
    Copyright (c) 2012 - 2013 by Alexander Pick (ap@pbt-media.com)
    and for the SSH version by marmalade Team <support@marmalade.de>

    Permission is hereby granted, free of charge, to any person obtaining a copy of this
    software and associated documentation files (the "Software"), to deal in the Software
    without restriction, including without limitation the rights to use, copy, modify, merge,
    publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons
    to whom the Software is furnished to do so, subject to the following conditions:
    
    The above copyright notice and this permission notice shall be included in all copies or
    substantial portions of the Software.
    
    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
    INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
    PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE
    FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
    OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
    OTHER DEALINGS IN THE SOFTWARE.
    
    More on Sputnik! at https://www.pbt-media.com
    
    ====================================================
 *
 * This version is extended by marmalade for our use to clone a complete version
 * of a live system into a stage or dev system.
 *
 * @author Alexander Pick <ap@pbt-media.com>
 * @author Joscha Krug <support@marmalade.de>
 *
 */

ini_set('max_execution_time', 0);
ini_set('memory_limit', -1);
set_time_limit(0);

/**
 * Small class to import the config.inc.php settings
 */
class config
{
    // Thats hardcoded for Profihost
    // ToDo: Make configurable
    public $mysqlPath = '/usr/local/mysql5/bin/';
    
    // AES Schlüssel
    public $sKey = null;
    
    public function __construct()
    {
        if (file_exists(dirname(__FILE__)."/config.inc.php")) {
            require(dirname(__FILE__)."/config.inc.php");
        }
    }
    
    public function getRequestParameter($name)
    {
        if (!isset($_REQUEST[$name])) {
            return false;
        }
        
        return stripslashes(htmlentities($_REQUEST[$name]));
    }
    
    public function getServerParameter($name)
    {
        if (!isset($_SERVER[$name])) {
            return false;
        }
        
        return stripslashes(htmlentities($_SERVER[$name]));
    }
}

/**
 * Everything you need for database operations
 */
class database
{
}

/**
 * Everything for the file handling copying etc.
 * is collected here.
 */
class filehandling
{
    /**
     * @param object $config
     * @param bool $excludeAllPictures Exclude the Pictures from the Backup
     */
    public function writeBackupShellscript(config $config, $excludeAllPictures = false)
    {
        $filename = 'backup_' . $config->sKey . '.sh';
        
        $content  = "#!/bin/bash\n";
        $content .= "[mysqlpath]mysql [name] -u [user] -p[password] -e 'show tables where tables_in_[name] not like \"oxv\_%\"' | grep -v Tables_in | xargs [mysqlpath]mysqldump [name] -u [user] -p[password] > ../backup_[hash].sql\n";
        $content .= "tar -czf ../backup_[hash].tar.gz . --exclude=tmp/*";
         
        if ($excludeAllPictures) {
            $content .= " --exclude=out/pictures/* \n";
        } else {
            $content .= " --exclude=out/pictures/generated/* \n";
        }
        
        $content .= "touch backup_finished_[hash].txt\n";
        $content .= "rm drone_[hash].php\n";
        $content .= 'rm backup_[hash].sh';
        
        $content = str_replace('[mysqlpath]', $config->mysqlPath, $content);
        $content = str_replace('[user]', $config->dbUser, $content);
        $content = str_replace('[host]', $config->dbHost, $content);
        $content = str_replace('[name]', $config->dbName, $content);
        $content = str_replace('[password]', $config->dbPwd, $content);
        $content = str_replace('[hash]', $config->sKey, $content);
        
        $res = file_put_contents($filename, $content);
        
        return $res;
    }
}

class ftp
{

    protected $config = null;
    
    protected $connection = null;

    public function __construct(config $config)
    {
        $this->config = $config;
    }
    
    /**
     * Copy the given file to the remote host
     *
     * @param object $config
     * @param string $localFilePath
     * @return boolean
     */
    public function copyFileToRemoteHost($localFilePath, $remoteFileName)
    {
        $connection = $this->startFtpConnection();
        
        $upload = false;
        
        $path = $config->getRequestParameter("ftpPath");
        
        if(false != $connection){
            ftp_chdir($connection, $path);
            
            $upload = ftp_put($connection, $remoteFileName, $localFilePath, FTP_BINARY);
        }
        
        return $upload;
    }
    
    public function startFtpConnection()
    {
        if(null !== $this->connection)
        {
            return $this->connection;
        }
        
        $ftpServer  = $this->config->getRequestParameter("ftpServer");
        $ftpUser    = $this->config->getRequestParameter("ftpUser");
        $ftpPass    = $this->config->getRequestParameter("ftpPass");
        
        $connection = ftp_connect($ftpServer);
        
        $login = ftp_login($connection, $ftpUser, $ftpPass);
        
        if (!$connection || !$login) {
            echo "Could not connect to remote host! Please check your ftp data.\n";
            return false;
        }
        
        ftp_pasv($connection, true);
        
        echo "Sucessfully connected to remote host.\n";
        
        $this->connection = $connection;
        
        return $connection;
    }
    
    public function downloadBackupedFile($filename)
    {
        $connection = $this->startFtpConnection();
        
        ftp_chdir($connection, $this->config->getRequestParameter('ftpPath'));
        
        ftp_cdup($connection);
        
        $downloaded = ftp_get($connection, $filename, $filename, FTP_BINARY);
        
        return $downloaded;
    }
    
    public function deleteFile($filename, $path)
    {
        $connection = $this->startFtpConnection();
        
        $res = ftp_delete($connection, $path . $filename);
        
        return $res;
    }
}

class drone
{
    protected $config = null;
    
    protected $ftp = null;
    
    protected $filehandler = null;


    public function __construct(config $config, ftp $ftp, filehandling $filehandler)
    {
        $this->config = $config;
        $this->ftp = $ftp;
        $this->filehandler = $filehandler;
    }

    /**
     * Prepare the drone file
     * Copy to livesystem
     *
     * @param object $config
     * @return bool
     */
    public function launchDrone()
    {
        $buffer = file_get_contents(__FILE__);
        //$array = explode('//drone end', $buffer);
        //$droneContent = $array[0];
        $droneContent = $buffer;
        
        $filename = $this->getDroneFilename();
    
        file_put_contents($this->getDroneLocalPath(), $droneContent);
        
        $res = $this->ftp->copyFileToRemoteHost($this->getDroneLocalPath(), $filename);
        
        unlink($this->getDroneLocalPath());
        
        return $res;
    }
    
    public function removeStatusfile()
    {
        $statusfile = 'backup_finished_' . $this->config->sKey . '.txt';
        
        $path = $this->config->getRequestParameter('ftpPath');
                
        $this->ftp->deleteFile($statusfile, $path);
    }
    
    public function getDroneLocalPath()
    {
        $droneName = $this->getDroneFilename();
        
        return dirname(__FILE__) . '/' . $droneName;
    }


    public function getDroneFilename()
    {
        $filename = 'drone_' . $this->config->sKey . '.php';
    
        return $filename;
    }
    
    public function startRemoteOperation()
    {
        $url  = $this->config->getRequestParameter('shopUrl');
        $url .= '/' . $this->getDroneFilename();
        $url .= '?drone=activate';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,1);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $data = curl_exec($ch);
        curl_close($ch);
        if(false !== strpos($data, '200 OK') || empty($data)){
            return true;
        } else {
            return false;
        }
    }
    
    public function startNow()
    {
        $res = $this->filehandler->writeBackupShellscript($this->config, true);
    
        if ($res === false) {
            exit('Problems writing the file');
        }

        exec('sh backup_' . $this->config->sKey . '.sh > /dev/null 2>&1');

        exit(0);
    }
}

class host
{
    protected $config = null;
    
    protected $ftp = null;
    
    protected $filehandler = null;
    
    protected $drone = null;


    public function __construct(config $config, ftp $ftp, filehandling $filehandler, $drone)
    {
        $this->config = $config;
        $this->ftp = $ftp;
        $this->filehandler = $filehandler;
        $this->drone = $drone;
    }
    
    public function startHostOperation()
    {
        switch ($this->config->getRequestParameter('spaceStep')) {
            case 1:
                $launched = $this->drone->launchDrone();
                if ($launched) {
                    echo "Placed the drone to the source.\n\n";
                } else {
                    echo "Drone not landed. Please check the path.\nFinished\n\n";
                }
                exit();
            case 2:
                $operating = $this->drone->startRemoteOperation();
                if($operating) {
                    echo "Started backup\n\n";
                } else {
                    echo "Could not start the remote operation (see message above).\nFinished.\n\n";
                }
                exit();
            case 3:
                $finished = $this->checkIfBackupIsFinished();
                if (!$finished) {
                    echo "Checked the backup. Not yet done.\n";
                    echo "Wait a bit\n\n";
                    flush();
                    sleep(10);
                } else {
                    echo "Backup done.\n\n";
                }   
                exit();
            case 4:
                echo "Downloading\n";
                flush();
                $this->drone->removeStatusfile();
                $downloaded = $this->downloadBackupfiles();
                exit();
            case 5:
                sleep(1);
                echo "Import DB\n";
                //import Db
                exit();
            case 6:
                sleep(1);
                echo "Anonymize the DB\n";
                // anonymize DB if requested
                exit();
            case 7:
                sleep(1);
                echo "Extract tar\n";
                // extract tar
                // rename .htaccess to _.htaccess
                exit();
            case 8:
                sleep(1);
                echo "Redefine Config\n";
                // redefine the values in config.inc.php for database, host and path
                // rerename _.htaccess
                exit();
            case 9:
                //Delete self
                echo "Finished\n";
                exit();
        }
    }
    
    public function downloadBackupfiles()
    {
        $sqlfile = 'backup_' . $this->config->sKey . '.sql';
        
        $tararchive = 'backup_' . $this->config->sKey . '.tar.gz';
        
        $this->ftp->downloadBackupedFile($sqlfile);
        
        $this->ftp->downloadBackupedFile($tararchive);
    }
    
    public function checkIfBackupIsFinished()
    {
        $url  = $this->config->getRequestParameter('shopUrl');
        $url .= '/backup_finished_' . $this->config->sKey . '.txt';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,1);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $data = curl_exec($ch);
        curl_close($ch);
        if(false !== strpos($data, '200 OK')){
            return true;
        } else {
            return false;
        }
    }
}

/**
 * Preparation of objects
 */
$config = new config();
$filehandler = new filehandling();
$ftp    = new ftp($config);
$drone  = new drone($config, $ftp, $filehandler);
$host   = new host($config, $ftp, $filehandler, $drone);

/**
 * Part for the initial start.
 * Rename this file and set the key.
 * Redirect to the new file.
 */
if (null === $config->sKey) {
    $sputnikFileName = md5(microtime());
    
    $sputnik = file_get_contents(__FILE__);
        
    $sputnik = str_replace('public $sKey = null;', 'public $sKey = "' . md5(rand().microtime()) . '";', $sputnik);
    
    file_put_contents(dirname(__FILE__) ."/".$sputnikFileName.".php", $sputnik);
    
    // ToDo: Change when finished
    //unlink(__FILE__);
    
    $redirectUrl = "http://".$_SERVER["HTTP_HOST"].dirname($_SERVER["REQUEST_URI"]).$sputnikFileName.".php";
    
    header("Location: " . $redirectUrl);
    
    exit(0);
}

/**
 * Part for the drone
 */
if ($config->getRequestParameter('drone') === 'activate') {
    $drone->startNow();
    exit();
}

/**
 * Everything from here is just needed for the local version
 */
if (1 == $config->getRequestParameter('ajax')) {
    $host->startHostOperation();
    exit();
}

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Datum in der Vergangenheit

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Sputnik SSH - Copy your Livesystrem to Stage or Dev</title>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js"></script>
<style type="text/css">
* {
    font-family: Arial, Helvetica, sans-serif;
    font-size:0.98em;
    color:#222;
}
label {
    display:block;
    float:left;
    clear:both;
    width:10em;
}
input[type='text'],
input[type='password'],
input[type='checkbox'] {
    border: 1px solid #ccc;
    width:10em;
    float:left;
    margin-bottom:0.5em;
}

h2,
#todo,
#copyright {
    clear:both;
}

#result {
    border:solid 1px #ccc;
    padding:0.2em;
    overflow:auto;
    max-height:10em;
}
#copyright {
    font-size:0.7em;
    color:#444;
}
</style>
<script language="javascript">

    function clone(step)
    {
        var host 	= $("#host").val();
        var user 	= $("#user").val();
        var name 	= $("#name").val();
        var pass 	= $("#pass").val();
        var ftpServer 	= $("#ftpServer").val();
        var ftpUser 	= $("#ftpUser").val();
        var ftpPass 	= $("#ftpPass").val();
        var ftpPath 	= $("#ftpPath").val();
        var shopUrl 	= $("#shopUrl").val();
        var anonymize 	= $("#anonymize").val();

        $.post("<?php $config->getServerParameter('SCRIPT_NAME') ?>", {
            'ajax'      : '1',
            'spaceStep' : step,
            'host'	: host,
            'user'	: user,
            'name'	: name,
            'pass'	: pass,
            'ftpServer'	: ftpServer,
            'ftpUser'	: ftpUser,
            'ftpPass'	: ftpPass,
            'ftpPath'	: ftpPath,
            'shopUrl'	: shopUrl,
            'anonymize'	: anonymize
        },
        function(resdata){
            $("#result pre").append(resdata);
            if(resdata.match(/Wait a bit/) && !resdata.match(/Finished/) )
            {
                clone(step);
            } else {
                if(step != 9 && !resdata.match(/Finished/)) {
                    step++;
                    clone(step);
                }  
            }
        });
    }
    
    var isShown = 0;
        
    function startClone() {
        
        $("#todo").fadeOut("slow");
        
        $("#host").prop('disabled', true);
        $("#user").prop('disabled', true);
        $("#name").prop('disabled', true);
        $("#pass").prop('disabled', true);
        $("#ftpServer").prop('disabled', true);
        $("#ftpUser").prop('disabled', true);
        $("#ftpPass").prop('disabled', true);
        $("#ftpPath").prop('disabled', true);
        $("#shopUrl").prop('disabled', true);
        $("#anonymize").prop('disabled', true);
        
        if(isShown == 0) {
            isShown = 1;
            clone(1);
        }
    }
</script>
</head>
<body>
<div id="main">
<h1 id="head">
    Sputnik SSH - Make sure you have the right data.
</h1>
<div id="result"><pre></pre></div>
<div id="tab1">
    <h2>Target system</h2>
    <label for="host">Host:</label>
    <input name="host" type="text" id="host" value="localhost" />
    <label for="name">DB Name:</label>
    <input name="name" type="text" id="name" />
    <label for="user">DB User:</label>
    <input name="user" type="text" id="user" />
    <label for="pass">DB Password:</label>
    <input name="pass" type="password" id="pass" />
    <label for="anonymize">Anonymize the data:</label>
    <input name="anonymize" type="checkbox" value="1" id="anonymize" checked />
</div>
<div id="tab2">
    <h2>Source system</h2>
    <label for="ftpServer">FTP Host:</label>
    <input name="ftpServer" type="text" id="ftpServer" value="localhost" />
    <label for="ftpUser">FTP User:</label>
    <input name="ftpUser" type="text" id="ftpUser" />
    <label for="ftpPass">FTP Password:</label>
    <input name="ftpPass" type="password" id="ftpPass" />
    <label for="ftpPath">FTP Path:</label>
    <input name="ftpPath" type="text" id="ftpPath" value="/httpdocs/" />
    <label for="shopUrl">Shop URL:</label>
    <input name="shopUrl" type="text" value="http://" id="shopUrl" />
    <label for="excludePics">Exclude pictures:</label>
    <input name="excludePics" type="checkbox" value="1" id="excludePics" checked disabled />
</div>
<div id="todo">
    <p>
        <button onclick="javascript:startClone();">
            Clone shop from source to target now.
        </button>
    </p>
</div>
</div>
<div id="copyright">
    Sputnik SSH for OXID by <a href="http://www.pbt-media.com" target="_blank">Alexander Pick</a> and <a href="http://www.marmalade.de/">marmalade</a>
    <br />
    <br />
    <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
        <input type="hidden" name="cmd" value="_donations" />
        <input type="hidden" name="business" value="webmaster@ttyseven.com" />
        <input type="hidden" name="lc" value="DE" />
        <input type="hidden" name="no_note" value="0" />
        <input type="hidden" name="currency_code" value="EUR" />
        <input type="hidden" name="bn" value="PP-DonationsBF:btn_donate_SM.gif:NonHostedGuest" />
        <input type="image" src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="Jetzt einfach, schnell und sicher online bezahlen – mit PayPal." />
        <img alt="" border="0" src="https://www.paypalobjects.com/de_DE/i/scr/pixel.gif" width="1" height="1" />
    </form>
</div>
</body>
</html>
