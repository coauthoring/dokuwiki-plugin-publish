<?php

if(!defined('DOKU_INC')) die();

class action_plugin_publish_approve extends DokuWiki_Action_Plugin {

    /**
     * @var helper_plugin_publish
     */
    private $helper;

    function __construct() {
        $this->helper = plugin_load('helper', 'publish');
    }

    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_io_write', array());
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'approveNS', array());
    }

    function approveNS(Doku_Event &$event, $param) {
        if ($event->data !== 'plugin_publish_approveNS') {
            return;
        }
        //no other ajax call handlers needed
        $event->stopPropagation();
        $event->preventDefault();

        //e.g. access additional request variables
        global $INPUT; //available since release 2012-10-13 "Adora Belle"
        $namespace = $INPUT->str('namespace');
        $pages = $this->helper->getPagesFromNamespace($namespace);
        $pages = $this->helper->removeSubnamespacePages($pages, $namespace);

        global $ID, $INFO;
        $original_id = $ID;
        foreach ($pages as $page) {
            $ID = $page[0];
            $INFO = pageinfo();
            if (!$this->helper->canApprove()) {
                continue;
            }
            $this->addApproval();
        }
        $ID = $original_id;
    }

    function handle_io_write(Doku_Event &$event, $param) {
        # This is the only hook I could find which runs on save,
        # but late enough to have lastmod set (ACTION_ACT_PREPROCESS
        # is too early)
        global $ACT;
        global $INPUT;
        global $ID;

        if ($ACT != 'show') {
            return;
        }

        if (!$INPUT->has('publish_approve')) {
            return;
        }

        if (!$this->helper->canApprove()) {
            msg($this->getLang('wrong permissions to approve'), -1);
            return;
        }

        $this->addApproval();
        send_redirect(wl($ID, array('rev' => $this->helper->getRevision()), true, '&'));
    }

    function addApproval() {
        global $USERINFO;
        global $ID;
        global $INFO;

        if (!$INFO['exists']) {
            msg($this->getLang('cannot approve a non-existing revision'), -1);
            return;
        }

        $approvalRevision = $this->helper->getRevision();
        $approvals = $this->helper->getApprovals();

        if (!isset($approvals[$approvalRevision])) {
            $approvals[$approvalRevision] = array();
        }

        $approvals[$approvalRevision][$INFO['client']] = array(
            $INFO['client'],
            $_SERVER['REMOTE_USER'],
            $USERINFO['mail'],
            time()
        );

        /* contents copy hack */
        global $conf;
        if($conf['isdebugging']){
          file_put_contents($conf['debuglogfile'],"------\n",FILE_APPEND);//debug
        }
        $iscopysuccess=false;
        $iscopysuccess2=false;
        $iscopysuccess3=false;
        $iscopysuccess4=false;
        $pagelog = new PageChangeLog(getID());
        $cpfrev = $pagelog->getRevisions(0, 1);

        if($conf['isdebugging']){
          ob_start();
          var_dump($pagelog);
          var_dump($cpfrev);
          $buffer = ob_get_contents();
          ob_end_clean();
        }

        $frommetafn = metaFN($ID,'.meta');
        $pos = strrpos($frommetafn, '/');
        if ($pos!==false) $frommetadir = substr($frommetafn, 0, $pos+1);
        $frompagefn = wikiFN($ID);
        $pagetitle = noNS($ID);
        $namespace = getNS($ID);
        $distmetadir=$conf['copybeforeapprove']."/meta/".(strlen($namespace)>0?$namespace."/":"");
        $distatticdir=$conf['copybeforeapprove']."/attic/".(strlen($namespace)>0?$namespace."/":"");
        $distmetafn=$pagetitle.".meta";
        $changesfn=$pagetitle.".changes";
        $atticfn=$pagetitle.".".$cpfrev[0].".txt.gz";
        $fromatticfn=wikiFN($ID,$cpfrev[0]);
        $distpagedir=$conf['copybeforeapprove']."/pages/".(strlen($namespace)>0?$namespace."/":"");
        $distpagefn=$pagetitle.".txt";

        $conn_id = null;
        if($conf['iscopyremote']){ //try copying to remote server via ftp
          $conn_id = ftp_login($conf['ftp_server'], $conf['ftp_user_name'], $conf['ftp_user_pass']);
          if (!$conn_id && $conf['isdebugging']) { //debug
            file_put_contents($conf['debuglogfile'],"------\n",FILE_APPEND);
            file_put_contents($conf['debuglogfile'],"can not login\n",FILE_APPEND);
            file_put_contents($conf['debuglogfile'],$conf['ftp_server']."\n",FILE_APPEND);
            file_put_contents($conf['debuglogfile'],$conf['ftp_user_name']."\n",FILE_APPEND);
            file_put_contents($conf['debuglogfile'],$conf['ftp_user_pass']."\n",FILE_APPEND);
            ftp_close($conn_id);
            die("can't login");
          }
          if(!ftp_chdir($conn_id, $distmetadir))ftp_mkdir($conn_id,$distmetadir);
          if(!ftp_chdir($conn_id, $distatticadir))ftp_mkdir($conn_id,$distatticadir);
          if(!ftp_chdir($conn_id, $distpagedir))ftp_mkdir($conn_id,$distpagedir);
        }else{ // try local copy
          if(!file_exists($distmetadir))mkdir($distmetadir,0755,true);
          if(!file_exists($distatticadir))mkdir($distatticadir,0755,true);
          if(!file_exists($distpagedir))mkdir($distpagedir,0755,true);
        }

        if($conf['isdebugging']){ //debug
          file_put_contents($conf['debuglogfile'],"------\n",FILE_APPEND);
          file_put_contents($conf['debuglogfile'],$ID ."\n",FILE_APPEND);
          file_put_contents($conf['debuglogfile'],$fromatticfn ."\n",FILE_APPEND);
          file_put_contents($conf['debuglogfile'],$distatticdir ."\n",FILE_APPEND);
          file_put_contents($conf['debuglogfile'],$atticfn ."\n",FILE_APPEND);
          file_put_contents($conf['debuglogfile'],"frompagefn ".$frompagefn ."\n",FILE_APPEND);
          file_put_contents($conf['debuglogfile'],"frommetadir ".$frommetadir ."\n",FILE_APPEND);
          file_put_contents($conf['debuglogfile'],"frommetafn ".$frommetafn ."\n",FILE_APPEND);
          file_put_contents($conf['debuglogfile'],"frommetachanges".$frommetadir.$changesfn."\n",FILE_APPEND);
          file_put_contents($conf['debuglogfile'],"namespace ".$namespace ."\n",FILE_APPEND);
          file_put_contents($conf['debuglogfile'],"pagetitle ".$pagetitle ."\n",FILE_APPEND);
          file_put_contents($conf['debuglogfile'],"distinations \n",FILE_APPEND);
          file_put_contents($conf['debuglogfile'],$distmetadir.$distmetafn."\n",FILE_APPEND);
          file_put_contents($conf['debuglogfile'],$distmetadir.$changesfn."\n",FILE_APPEND);
          file_put_contents($conf['debuglogfile'],$distpagedir.$distpagefn."\n",FILE_APPEND);
          file_put_contents($conf['debuglogfile'],$distatticdir.$atticfn."\n",FILE_APPEND);
          file_put_contents($conf['debuglogfile'],"------\n",FILE_APPEND);//debug
        }

        if($conf['iscopyremote']){
          $iscopysuccess = ftp_put($conn_id,$distpagedir.$distpagefn,$frompagefn);
          $iscopysuccess2 = ftp_put($conn_id,$distmetadir.$distmetafn,$frommetafn);
          $iscopysuccess3 = ftp_put($conn_id,$distmetadir.$changesfn,$frommetadir.$changesfn);
          $iscopysuccess4 = ftp_put($conn_id,$distatticdir.$atticfn,$fromatticfn);
        }else{
          $iscopysuccess = copy($frompagefn,$distpagedir.$distpagefn);
          $iscopysuccess2 = copy($frommetafn,$distmetadir.$distmetafn);
          $iscopysuccess3 = copy($frommetadir.$changesfn,$distmetadir.$changesfn);
          $iscopysuccess4 = copy($fromatticfn,$distatticdir.$atticfn);
        }

        if($conf['isdebugging']){ //debug
          if($iscopysuccess && $iscopysuccess2 && $iscopysuccess3 && $iscopysuccess4)
            file_put_contents($conf['debuglogfile'],$ID." copy success"."\n",FILE_APPEND);
          else
            file_put_contents($conf['debuglogfile'],$ID." copy failed"."\n",FILE_APPEND);
          file_put_contents($conf['debuglogfile'],$ID." passed"."\n",FILE_APPEND);
        }
        /* contents copy hack end */

        $success = p_set_metadata($ID, array('approval' => $approvals), true, true);
        if ($success) {
            msg($this->getLang('version approved'), 1);

            $data = array();
            $data['rev'] = $approvalRevision;
            $data['id'] = $ID;
            $data['approver'] = $_SERVER['REMOTE_USER'];
            $data['approver_info'] = $USERINFO;
            if ($this->getConf('send_mail_on_approve') && $this->helper->isRevisionApproved($approvalRevision)) {
                /** @var action_plugin_publish_mail $mail */
                $mail = plugin_load('action','publish_mail');
                $mail->send_approve_mail();
            }
            trigger_event('PLUGIN_PUBLISH_APPROVE', $data);
        } else {
            msg($this->getLang('cannot approve error'), -1);
        }

    }

}
