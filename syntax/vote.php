<?php
/**
 * Syntax Plugin:
 * A basic voting system between multiple candidates.
 *
 * the result is determined by the schulze method
 *
 * syntax:
 * <vote right 2010-04-10>
 *  candaidate A
 *  candaidate B
 *  candaidate C
 * </vote>
 *
 * @author Dominik Eckelmann <eckelmann@cosmocode.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_schulzevote_vote extends DokuWiki_Syntax_Plugin {

    function getType(){ return 'substition'; }
    function getPType(){ return 'block'; }
    function getSort(){ return 155; }

    function connectTo($mode) {
         $this->Lexer->addSpecialPattern('<vote[ a-z0-9-]*?>\n.*?\n</vote>',$mode,'plugin_schulzevote_vote');
    }

    function handle($match, $state, $pos, &$handler){
        $lines = explode("\n", $match);

        $opts = array();
        // Determine date from syntax
        $opts['date'] = null;

        if (preg_match('/\d{4}-\d{2}-\d{2}/', $lines[0], $opts['date'])) {

            $opts['date'] = strtotime($opts['date'][0]);
            if ($opts['date'] === false || $opts['date'] === -1) {
                $opts['date'] = null;
            }
        }

        // Determine align informations
        $opts['align'] = 'left';
        if (preg_match('/(left|right|center)/',$lines[0], $align)) {
            $opts['align'] = $align[0];
        }

        unset($lines[count($lines)-1]);
        unset($lines[0]);

        $candidates = array();

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $candidates[] = $line;
            }
        }

        $hlp = plugin_load('helper', 'schulzevote');
        $hlp->createVote($candidates);

        return array('candy' => $candidates, 'opts' => $opts);
    }

    function render($mode, &$renderer, $data) {

        if ($mode != 'xhtml') return false;

        if (isset($_POST['vote']) && checkSecurityToken()) {
            $this->_handlepost($data);
        }
        $this->_html($renderer, $data);
    }

    function _html(&$renderer, $data) {

        global $ID;

        // set alignment
        $align = $data['opts']['align'];

        $hlp = plugin_load('helper', 'schulzevote');
#        dbg($hlp);

        // check if the vote is over.
        $open = ($data['opts']['date'] !== null) && ($data['opts']['date'] > time());
        if ($open) {
            $renderer->info['cache'] = false;
            if (!isset($_SERVER['REMOTE_USER'])) {
                $open = false;
                $closemsg = $this->getLang('no_remote_user');
            } elseif ($hlp->hasVoted()) {
                $open = false;
                $closemsg = $this->getLang('already_voted');
            }
            $closemsg .= '<br />'.$this->_winnerMsg($hlp, 'leading');
        } else {
            $closemsg = $this->getLang('vote_over').'<br />'.
                        $this->_winnerMsg($hlp, 'has_won');
        }

        $form = new Doku_Form(array('id'=>'plugin__schulzevote', 'class' => 'plugin_schulzevote_'.$align));
        $form->startFieldset($this->getLang('cast'));

        if ($open) {
            $form->addHidden('id', $ID);
            foreach ($data['candy'] as $n => $candy) {
                $form->addElement(form_makeTextField('vote[' . $n . ']',
                                  isset($_POST['vote']) ? $_POST['vote'][$n] : '', $candy,'', 'block'));
            }
            $form->addElement('<p>'.$this->getLang('howto').'</p>');
            $form->addElement(form_makeButton('submit','', 'Vote!'));
            $form->addElement($this->_winnerMsg($hlp, 'leading'));
        }else{
            foreach ($data['candy'] as $candy) {
                $form->addElement('<p class="candy">' . hsc($candy) . '</p>');
            }
            $form->addElement('<p>' . $closemsg . '</p>');
        }

        $form->endFieldset();
        $renderer->doc .=  $form->getForm();

        return true;
    }

    function _winnerMsg($hlp, $lang) {
        $winner = $hlp->getWinner();
        return !is_null($winner) ? sprintf($this->getLang($lang), $winner) : '';
    }

    function _handlepost($data) {
        $err = false;
        foreach($_POST['vote'] as $n => &$vote) {
            if ($vote !== '' && !is_numeric($vote)) {
                msg(sprintf($this->getLang('invalid_vote'), $data['candy'][$n]), -1);
                $vote = '';
                $err = true;
            }
        }
        if ($err || count(array_filter($_POST['vote'])) === 0) return;
        $hlp = plugin_load('helper', 'schulzevote');
        $hlp->vote(array_combine($data['candy'], $_POST['vote']));
        msg($this->getLang('voted'), 1);
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :