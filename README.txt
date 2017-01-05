Plug-in to allow simple page approval process.

2017-1-5 coauthoring
Purpose: Introducing hierarchical approval process and/or
         more secure internal draft protection.
Feature: Allowing this plugin copying an approved page to
         a specified other dokuwiki (e.g. public site to internet)
Modify:  Modified in action/approve.php
Usage:   set configuration below in conf/local.php (Please manually
         add those code.)
-----
$conf['debuglogfile'] = '/path/to/dokuwiki/log/debuglog.txt';
$conf['copybeforeapprove'] = '/path/to/dokuwiki/data'; //data directory path without "/"
$conf['isdebugging'] = true; //set false if you do not need the log.
$conf['iscopyremote'] = false; //set true if you use ftp for remote site
$conf['ftp_server'] = 'dokuwiki.my-remote-site.com';
$conf['ftp_user_name'] = 'ftp-user';
$conf['ftp_user_pass'] = 'ftp-user-password';
$conf['remoteftport'] = 21; //set ftp port
$conf['remotefttimeout'] = 90; //set timeout (second)
-----
         When you approve a page in your primary (drafting) dokuwiki,
         it will be copied to the secondary (publishing) dokuwiki as a
         non-approved page. If the page be approved again on the
         secondary site, the page is publised on the site.
Future work:
         Plugin configuration forms.

