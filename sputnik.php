<?php
/**

    Sputnik! Swiss Army Knife for OXID eShop
    ====================================================
    
    Sputnik! is free to use, please consider a donation
    if you are using this software.
    
    LICENSE
    
    Copyright (c) 2012 - 2013 by Alexander Pick (ap@pbt-media.com)

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
    
    public $useAES = true;
            
    public function __construct()
    {
        if(file_exists(dirname(__FILE__)."/config.inc.php")){
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
 * Collection of all methods according to the encryption
 */
class crypt
{
    public $useAES = true;
    
    /**
     * Encrypt the given content if defined
     *
     * @param type $sValue
     * @param type $sSecretKey
     * @return type
     */
    public function aesEncrypt($sValue, $sSecretKey)
    {
        if ($this->useAES == false) {
            return $sValue;
        }

        return trim(
            base64_encode(
                mcrypt_encrypt(
                    MCRYPT_RIJNDAEL_256,
                    $sSecretKey, $sValue,
                    MCRYPT_MODE_ECB,
                    mcrypt_create_iv(
                        mcrypt_get_iv_size(
                            MCRYPT_RIJNDAEL_256,
                            MCRYPT_MODE_ECB
                        ),
                        MCRYPT_RAND)
                    )
                )
            );
    }
    
    /**
     * Decrypt the given content
     * @param type $sValue
     * @param type $sSecretKey
     * @return type
     */
    public function aesDecrypt($sValue, $sSecretKey)
    {
        if ($this->useAES == false) {
            return $sValue;
        }

        echo "Entschlüssele Backup<br />";

        return trim(
            mcrypt_decrypt(
                MCRYPT_RIJNDAEL_256,
                $sSecretKey,
                base64_decode($sValue),
                MCRYPT_MODE_ECB,
                mcrypt_create_iv(
                    mcrypt_get_iv_size(
                        MCRYPT_RIJNDAEL_256,
                        MCRYPT_MODE_ECB
                    ),
                    MCRYPT_RAND
                )
            )
        );
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
     * @param bool $excludeAllPictures Exclude the Pictures from the Backup
     * @param object $config
     */
    public function writeBackupShellscript(config $config, $excludeAllPictures = false)
    {
        $filename = 'backup_' . $config->sKey . '.sh';
        
        $content  = "#!/bin/bash\n";
        $content .= "rm [hash].php\n";
        $content .= "[mysqlpath]mysql [name] -u [user] -p[password] -e 'show tables where tables_in_[name] not like \"oxv\_%\"' | grep -v Tables_in | xargs [mysqlpath]mysqldump [name] -u [user] -p[password] > ../backup_[hash].sql\n";
        $content .= "tar -czf ../backup_[hash].tar.gz . --exclude=tmp/*";
         
        if ($excludeAllPictures) {
            $content .= " --exclude=out/pictures/* \n";
        } else {
            $content .= " --exclude=out/pictures/generated/* \n";
        }
        
        $content .= "touch backup_finished_[hash].txt";
        
        $content = str_replace('[mysqlpath]', $config->mysqlPath, $content);
        $content = str_replace('[user]', $config->dbUser, $content);
        $content = str_replace('[host]', $config->dbHost, $content);
        $content = str_replace('[name]', $config->dbName, $content);
        $content = str_replace('[password]', $config->dbPwd, $content);
        $content = str_replace('[hash]', $config->sKey, $content);
        
        $res = file_put_contents($filename, $content);
        
        return $res;
    }

    public function unpackFile($file_name)
    {
        return true;
    }

    public function downloadFile($url, $filename)
    {
        unlink(realpath(dirname(__FILE__)."/".$filename));

        echo "Download der neusten OXID CE Version ... <br />";

        $path = dirname(__FILE__)."/".$filename;

        $timeout = 920;

        $fp = fopen($path, 'w');

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FILE, $fp);

        $data = curl_exec($ch);

        curl_close($ch);
        fclose($fp);

        return $filename;
    }
}

/**
 * Copy the whole file to the remote host.
 * This is different to the original version.
 *
 * @return string name of the remote file
 */
function launchSputnik()
{
    $buffer = file_get_contents(dirname(__FILE__)."/sputnik.php");
    
    $filename = md5(microtime()).".php";
    
    file_put_contents(dirname(__FILE__)."/".$filename, $buffer);
    
    return $filename;
}

$config = new config();

$filehandler = new filehandling();

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
    $res = $filehandler->writeBackupShellscript($config, true);
    
    if ($res === false) {
        exit('Problems writing the file');
    }
    
    exec('sh backup_' . $config->sKey . '.sh > /dev/null 2>&1');
    
    exit(0);
}

if(1 == $config->getRequestParameter('ajax')){
    
    switch($config->getRequestParameter('spaceStep')){
        case 1:
            sleep(1);
            // Check local dbConnection
            // Check FTP Connection
            echo "All checks are fine\n";
            exit();
        case 2:
            sleep(1);
            // Copy file to FTP
            // Call the drone
            echo "Placed the drone to the source.\n Started backup\n";
            exit();
        case 3:
            sleep(1);
            // Check if export is finished
            // else sleep
            echo "Checked the backup. Not yet finished.\n";
            echo "Wait a bit\n";
            exit();
        case 4:
            sleep(1);
            echo "Downloading";
            // Download db and files
            // Remove drone from source
            exit();
        case 5:
            sleep(1);
            echo "Import DB";
            //import Db
            exit();
        case 6:
            sleep(1);
            echo "Anonymize the DB";
            // anonymize DB if requested
            exit();
        case 7:
            sleep(1);
            echo "Extract tar";
            // extract tar
            // rename .htaccess to _.htaccess
            exit();
        case 8:
            sleep(1);
            echo "Redefine Config";
            // redefine the values in config.inc.php for database, host and path
            // rerename _.htaccess
            exit();
        case 9:
            //Delete self
            echo "Finished\n";
            exit();
    }
    
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
input[type='password'] {
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
            'shopUrl'	: shopUrl
        },
        function(resdata){
            $("#result pre").append(resdata);
            if(resdata.match(/Wait a bit/) && !resdata.match(/Finished/) )
            {
                clone(step);
            } else {
                if(step != 9) {
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
    <h2>Zielsystem</h2>
    <label for="host">Host:</label>
    <input name="host" type="text" id="host" value="localhost" />
    <label for="name">DBName:</label>
    <input name="name" type="text" id="name" />
    <label for="user">DBUser:</label>
    <input name="user" type="text" id="user" />
    <label for="pass">DBPass:</label>
    <input name="pass" type="password" id="pass" />
</div>
<div id="tab2">
    <h2>Quellsystem</h2>
    <label for="ftpServer">FTP Host:</label>
    <input name="ftpServer" type="text" id="ftpServer" value="localhost" />
    <label for="ftpUser">FTP Benutzer:</label>
    <input name="ftpUser" type="text" id="ftpUser" />
    <label for="ftpPass">FTP Passwort:</label>
    <input name="ftpPass" type="password" id="ftpPass" />
    <label for="ftpPath">FTP Pfad:</label>
    <input name="ftpPath" type="text" id="ftpPath" value="/httpdocs/" />
    <label for="shopUrl">Shop URL:</label>
    <input name="shopUrl" type="text" value="http://" id="shopUrl" />
</div>
<div id="todo">
<p>Was möchten Sie tun?</p>
    <p>
        <button onclick="javascript:startClone();">
            Shop auf aktuelles Hosting clonen
        </button>
    </p>
</div>
</div>
<div id="copyright">
    Sputnik SSH for OXID by <a href="http://www.pbt-media.com" target="_blank">Alexander Pick</a> and <a href="http://www.marmalade.de/">marmalade</a>
    <br>
    <br>
    <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
        <input type="hidden" name="cmd" value="_donations">
        <input type="hidden" name="business" value="webmaster@ttyseven.com">
        <input type="hidden" name="lc" value="DE">
        <input type="hidden" name="no_note" value="0">
        <input type="hidden" name="currency_code" value="EUR">
        <input type="hidden" name="bn" value="PP-DonationsBF:btn_donate_SM.gif:NonHostedGuest">
        <input type="image" src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="Jetzt einfach, schnell und sicher online bezahlen – mit PayPal.">
        <img alt="" border="0" src="https://www.paypalobjects.com/de_DE/i/scr/pixel.gif" width="1" height="1">
    </form>
</div>
</body>
</html>
