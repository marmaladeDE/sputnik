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

//mark

ini_set('max_execution_time', 0);
ini_set('memory_limit', -1);
set_time_limit(0);

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Datum in der Vergangenheit

/**
 * Small class to import the config.inc.php settings
 */
class config
{
    // Thats hardcoded for Profihost
    // ToDo: Make configurable
    public $mysqlPath = '/usr/local/mysql5/bin/';
    
    // AES Schlüssel
    public $sKey    = "<sputnik_key>";
    public $useAES = true;
            
    public function __construct()
    {
        require(dirname(__FILE__)."/config.inc.php");
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
     *
     * @param object $config
     */
    public function writeBackupShellscript(config $config)
    {
        $filename = 'backup_' . $config->sKey . '.sh';
        
        $content  = "#!/bin/bash\n";
        $content .= "[mysqlpath]mysql [name] -u [user] -p[password] -e 'show tables where tables_in_[name] not like \"oxv\_%\"' | grep -v Tables_in | xargs [mysqlpath]mysqldump [name] -u [user] -p[password] > ../backup_[hash].sql\n";
        $content .= "tar -czf ../backup_[hash].tar.gz . --exclude=out/pictures/generated/\n";
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


function hexToStr($hex)
{
    $string='';
    for ($i=0; $i < strlen($hex)-1; $i+=2) {
        $string .= chr(hexdec($hex[$i].$hex[$i+1]));
    }
    return $string;
}

/*
 * Rename this file and set the $firstStart option
 * Redirect to the new file
if (!isset($firstStart)) {
    $sputnikFileName = md5(microtime());

    $sputnik = file_get_contents(__FILE__);
        
    $sputnik = str_replace("<sputnik_key>", md5(rand().microtime()), $sputnik);
    $sputnik = str_replace("//mark", '$firstStart = 1;', $sputnik);
        
    file_put_contents(dirname(__FILE__) ."/".$sputnikFileName.".php", $sputnik);

    unlink(__FILE__);
    
    header("Location: http://".$_SERVER["HTTP_HOST"].dirname($_SERVER["REQUEST_URI"])."/".$sputnikFileName.".php");
    
    exit(0);
}

if ($_REQUEST["ajax"] == 1) {
    if ($_REQUEST["randomize"] == 1) {
        echo md5(microtime());
    }
    
    if ($_REQUEST["spaceStep"] == 1) {
        echo "Drohne wird gestartet ...<br />";
        echo "Erstelle Dump der remote DB <br />";
    }
    
    if ($_REQUEST["spaceStep"] == 2) {
        $sputnikDrone = launchSputnik();
        
        $connection = ftp_connect($_REQUEST["ftpServer"]);
    
        $login = ftp_login($connection, $_REQUEST["ftpUser"], $_REQUEST["ftpPass"]);
    
        if (!$connection || !$login) {
            unlink(dirname(__FILE)."/".$sputnikDrone);
            die('Sputnik! konnte sich nicht zum FTP Host verbinden!<br />');
        }
        
        ftp_pasv($connection, true);
    
        $upload = ftp_put($connection, $_REQUEST["ftpPath"]."/".$sputnikDrone, dirname(__FILE__)."/".$sputnikDrone, FTP_BINARY);
    
        unlink(dirname(__FILE__)."/".$sputnikDrone);
    
        if (!$upload) {
            die('FTP upload Fehler!<br />');
        }
        
        file_get_contents($_REQUEST["shopUrl"]."/".$sputnikDrone);
        
        ftp_delete($connection, $_REQUEST["ftpPath"]."/".$sputnikDrone);
                
        echo "MySQL dump ausgeführt, importiere Datenbank ...<br />";
    }
    
    if ($_REQUEST["spaceStep"] == 3) {
        $timestamp = time();
        
        $connection = ftp_connect($_REQUEST["ftpServer"]);
    
        $login = ftp_login($connection, $_REQUEST["ftpUser"], $_REQUEST["ftpPass"]);
        
        $remoteFile = $_REQUEST["ftpPath"]."/tmp/backup-".date('dmY', $timestamp).".sql";
        
        ftp_get($connection, dirname(__FILE__)."/backup-".date('dmY', $timestamp).".sql", $remoteFile, FTP_BINARY);
        
        ftp_delete($connection, $remoteFile);
        
        ftp_close($connection);
        
        importDb($_REQUEST["user"], $_REQUEST["pass"], $_REQUEST["host"], $_REQUEST["name"], dirname(__FILE__)."/backup-".date('dmY', $timestamp).".sql");
        
        unlink(dirname(__FILE__)."/backup-".date('dmY', $timestamp).".sql");
    
        echo "Hole Shopdaten vom Remote Server<br />";
    }
    
    if ($_REQUEST["spaceStep"] == 4) {
        $connection = ftp_connect($_REQUEST["ftpServer"]);
    
        $login = ftp_login($connection, $_REQUEST["ftpUser"], $_REQUEST["ftpPass"]);
        
        ftp_chdir($connection, $_REQUEST["ftpPath"]);
        
        recDownload(dirname(__FILE__), "./", $connection);
                
        ftp_close($connection);
        
        echo "Shopdaten übertragen, räume auf<br />";
    }
        
    if ($_REQUEST["spaceStep"] == 5) {
        rrmdir(dirname(__FILE__)."/tmp");
        mkdir(dirname(__FILE__)."/tmp", 0777);
        //mod config
        
        chmodRec(dirname(__FILE__)."/export/", 0777, 0777);
        
        chmod(dirname(__FILE__).'/.htaccess', 0777);
        chmod(dirname(__FILE__)."/config.inc.php", 0777);
        
        chmodRec(dirname(__FILE__)."/log/", 0777, 0777);
        chmodRec(dirname(__FILE__)."/out/", 0777, 0777);
        
        $config = file_get_contents(dirname(__FILE__)."/config.inc.php");
                
        $config = preg_replace('#this\-\>dbHost \= \'(.*)\'\;#i', "this->dbHost = '".$_REQUEST["host"]."';", $config);
        $config = preg_replace('#this\-\>dbName \= \'(.*)\'\;#i', "this->dbName = '".$_REQUEST["name"]."';", $config);
        $config = preg_replace('#this\-\>dbUser \= \'(.*)\'\;#i', "this->dbUser = '".$_REQUEST["user"]."';", $config);
        $config = preg_replace('#this\-\>dbPwd \= \'(.*)\'\;#i', "this->dbPwd = '".$_REQUEST["pass"]."';", $config);

        $config = preg_replace('#this\-\>sShopURL \= \'(.*)\'\;#i', "this->sShopURL = 'http://".$_SERVER["HTTP_HOST"].dirname($_SERVER["REQUEST_URI"])."';", $config);
        $config = preg_replace('#this\-\>sSSLShopURL \= \'(.*)\'\;#i', "this->sSSLShopURL = null;", $config);
        $config = preg_replace('#this\-\>sAdminSSLURL \= \'(.*)\'\;#i', "this->sAdminSSLURL = null;", $config);

        $config = preg_replace('#this\-\>sShopDir \= \'(.*)\'\;#i', "this->sShopDir = '".dirname(__FILE__)."';", $config);
        $config = preg_replace('#this\-\>sCompileDir \= \'(.*)\'\;#i', "this->sCompileDir = '".dirname(__FILE__)."/tmp';", $config);

        file_put_contents(dirname(__FILE__)."/config.inc.php", $config);
        
        echo "Dateirechte wurden gesetzt, die Config Datei wurde bearbeitet<br />";
        
        echo 'OXID Shop erfolgreich kopiert, <a href="http://'.$_SERVER["HTTP_HOST"].dirname($_SERVER["REQUEST_URI"]).'/admin/" target="_blank">hier klicken</a> um zum Shop zu gelangen<br />';
        echo "Bitte loggen Sie sich ein und updaten Sie die Views<br />";
    }
    
    exit(1);
}

$starImage = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADUAAAA3CAYAAACsLgJ7AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyRpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYxIDY0LjE0MDk0OSwgMjAxMC8xMi8wNy0xMDo1NzowMSAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNS4xIE1hY2ludG9zaCIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDo2Q0MxQkY5Rjk2RkYxMUUyOTdDOEFEQkYyOUYxRkFDNyIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDo2Q0MxQkZBMDk2RkYxMUUyOTdDOEFEQkYyOUYxRkFDNyI+IDx4bXBNTTpEZXJpdmVkRnJvbSBzdFJlZjppbnN0YW5jZUlEPSJ4bXAuaWlkOjZDQzFCRjlEOTZGRjExRTI5N0M4QURCRjI5RjFGQUM3IiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOjZDQzFCRjlFOTZGRjExRTI5N0M4QURCRjI5RjFGQUM3Ii8+IDwvcmRmOkRlc2NyaXB0aW9uPiA8L3JkZjpSREY+IDwveDp4bXBtZXRhPiA8P3hwYWNrZXQgZW5kPSJyIj8+dwV+NQAAAwBJREFUeNrcmk9IVFEUxq8pwoAyIRhCMBAEyoAgDLiTYmDaBC7ENrYJBoJWgRAErlq1mjbJRGAUgW4CQTFaCYIRCIrCgBgMSlEkSUEYSaFM37Hz4PXwzft3Zu69c+DHG59v7rzv3fvOPffco2pKKY20g8egU7JdnYJIyCyogZxk2+eUHkuBV2CC/x6SbFyHqC6wBEZd53Kiv9DkIdcD3vGQc7Nm6zvVBzbPEET8YqdhlagM2PER5DBok6O4DFZBf8B1OVscxSALyoS4dsgGUcNgBfSFvD4n6f16QV54ArwCDgPeIS+HUs6ijRrCcYfHPtl3sMfQ5w3wA1Rd5+rZdZ5YUzGe8QB4n7Sj2mr/jkUwE+F7WyyUbuAjOAAVkAVl0Bnzfm6COanJl+KwTxGHSyMoSbr0P+CR0m8iHtAZfk5Mtgt6NYqiIX1e0qX/BNOaeyrtclhi89Q0i7N6CHpFkbt+olnUbXBReulB0fRvzV6QovaHIC0ZpZcNcO/EN3A3ag7D7x+XwLEhwohdMCGxnpo1SJTDOseVsUVlDRTl8KbeojKoK+cNFkbM8Ko6kqhhw0Wd6SnDvHjLFgj7z1N2hJjKXoC8Mt96QOl02ROipxYs6akldm6BgrIWiNn0uvkgUc8NFvMBFKO69IwBMaBfgmYKpOLMUyXDxFDY9pQD7lgRRTpGiqvREUQ2aew3ZZATKEgsPWisftUs5oufE4gr6o5mQTQvdknuelC2dlJzZLCYNE/iFTUukc1JaBXJvB/Zuvj+azQ7Ad3gSKqnCpoFKc7NHyVtxC3qngGR9pZEIx2uBGIh4hPd52OVP29zyvplgtS1qKj7nvN0k5/5Rw54X2qbz1cD2hwByzETkiKinE23W3zD+7zJltQyLCyqJ73AD9HY4pB6dRN+UYTxJQfU61fB22YOvWaUHNB+0zXwupVEKZ53xkLs5YqJanbBVb2Nh36bixgfNLKGQmdl5mSrlMZ5Kbq2i8qtUG5K9gzc4HKHimTD3qWHDsvzXrOY9/srwABkSDcyh+P0ugAAAABJRU5ErkJggg=="

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Sputnik! Alx's Swiss Army Knife for OXID</title>
<link href='http://fonts.googleapis.com/css?family=Economica' rel='stylesheet' type='text/css'>
<link href='http://fonts.googleapis.com/css?family=Share+Tech+Mono' rel='stylesheet' type='text/css'>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js"></script>
<style type="text/css">
body,td,th {
    font-family: Arial, Helvetica, sans-serif;
}
body {
    margin-left: 0px;
    margin-top: 0px;
    margin-right: 0px;
    margin-bottom: 0px;
    width: 100%;
    background-color:#000;
    overflow:hidden;
    height:100%;
}
a {
    color:#FF0000;
    text-decoration:none;
}
#head {
    font-family: 'Economica', sans-serif;
    font-size:90px;
    margin:0;
    font-weight:bold;
    margin-bottom:30px;
}
#slogan {
    font-size: 15px;
}
input[type='text'],
input[type='password'] {
    border: 1px solid #CCC;
    width:180px;
}
#main {
    width: 400px;
    float: left;
}
#result {
    height: 425px;
    overflow:auto;
    border: 1px solid #CCC;
    margin-left:420px;
    margin-right:30px;
    display: none;
    background-color:#000;
    color:#00CC00;
    font-family: 'Share Tech Mono', sans-serif;
    padding:10px;
}

#frame {
    width: 100%;
    margin-top: 50px;
    background-color:#FFF;
    padding: 10px;
    height: 450px;
}
#tab1 {
    position: relative;
    left: 0;
}
#tab2 {
    position: relative;
    left: 0;
    display:none;
}
#tabhead {
    clear:both;
}
#copyright {
    font-size:10px;
    color:#FFF;
    text-align:right;
    padding-right:10px;
}
</style>
<script language="javascript">

    function switchTab(tab) {
        if(tab == 1) {
            $("#tab1").hide();
            $("#tab2").show();
        }
        if(tab == 0) {
            $("#tab2").hide();
            $("#tab1").show();
        }
    }

    function clone(step) {
        
            var host 		= $("#host").val();
            var user 		= $("#user").val();
            var name 		= $("#name").val();
            var pass 		= $("#pass").val();
            var ftpServer 	= $("#ftpServer").val();
            var ftpUser 	= $("#ftpUser").val();
            var ftpPass 	= $("#ftpPass").val();
            var ftpPath 	= $("#ftpPath").val();
            var shopUrl 	= $("#shopUrl").val();
        
            $.post("<?php $_SERVER['SCRIPT_NAME'] ?>", {
                    'ajax'	   			: 	'1',
                    'spaceStep'			:	step,
                    'host'				:	host,
                    'user'				:	user,
                    'name'				:	name,
                    'pass'				:	pass,
                    'ftpServer'			:	ftpServer,
                    'ftpUser'			:	ftpUser,
                    'ftpPass'			:	ftpPass,
                    'ftpPath'			:	ftpPath,
                    'shopUrl'			:	shopUrl,
            },
            function(resdata){
                $("#result").append(resdata);
                if(step != 6) {
                    step++;
                    clone(step);
                }
            });
    }
    
    var isShown = 0;
        
    function startClone() {
        
        endAnim = 1;
        
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
            $("#result").slideToggle('slow');
            clone(1);
        }
        }
</script>
</head>
<body>
<div id="frame">
<div id="main">
<div id="head"><img alt="" src="<?php echo $starImage; ?>" /> Sputnik! <img alt="" src="<?php echo $starImage; ?>" />
<span id="slogan"><center>"The only good is knowledge and the only evil is ignorance."<br /><i>Socrates (469 BC - 399 BC)</i></center></span>
</div>
<div id="tabhead"><a href="#" onclick="javascript:switchTab(0);">Datenbank</a> | <a href="#" onclick="javascript:switchTab(1);">FTP</a></div>
<div id="tab1">
<table width="320" border="0" id="options">
  <tr>
    <td width="160"> Host:
    </td>
    <td><input name="host" type="text" id="host" value="localhost" /></td>
  </tr>
  <tr>
    <td width="160">DBName:</td>
    <td><input name="name" type="text" id="name" /></td>
  </tr>
  <tr>
    <td width="160">DBUser:</td>
    <td><input name="user" type="text" id="user" /></td>
  </tr>
  <tr>
    <td width="160">DBPass:</td>
    <td><input name="pass" type="password" id="pass" /></td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td>&nbsp;</td>
  </tr>
</table>
</div>
<div id="tab2">
<table width="320" border="0">
  <tr>
    <td width="160"> FTP Host:</td>
    <td><input name="ftpServer" type="text" id="ftpServer" value="localhost" /></td>
  </tr>
  <tr>
    <td width="160">FTP Benutzer:</td>
    <td><input name="ftpUser" type="text" id="ftpUser" /></td>
  </tr>
  <tr>
    <td width="160">FTP Passwort:</td>
    <td><input name="ftpPass" type="password" id="ftpPass" /></td>
  </tr>
  <tr>
    <td width="160">FTP Pfad:</td>
    <td><input name="ftpPath" type="text" id="ftpPath" value="/httpdocs/" /></td>
  </tr>
    <tr>
    <td width="160">Shop URL:</td>
    <td><input name="shopUrl" type="text" value="http://" id="shopUrl" /></td>
  </tr>
</table>
</div>
<div id="todo">
<p>Was möchten Sie tun?</p>
<p><a href="#" onclick="javascript:startClone();">Shop auf aktuelles Hosting clonen</a></p>
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
</div>
<div id="result"></div>
</div>
<div id="copyright">Sputnik! for OXID (c) 2012-2013 Alexander Pick (ap@pbt-media.com) - <a href="http://www.pbt-media.com" target="_blank">http://www.pbt-media.com</a></div>
</body>
</html>
*/

$config = new config();

$filehandler = new filehandling();

/**
 * Part for the drone
 */
if ($_REQUEST['drone'] === 'activate') {
    $res = $filehandler->writeBackupShellscript($config);
    
    if ($res === false) {
        exit('Problems writing the file');
    }
    
    exec('sh backup_.' . $config->sKey . '.sh > /dev/null 2>&1');
}
