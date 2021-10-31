<?php
/**
 * Syntax Plugin:
 * A basic voting system between multiple candidates.
 *
 * the result is determined by the schulze method
 *
 * syntax:
 * <vote right 2010-04-10>
 *  candidate A
 *  candidate B
 *  candidate C
 * </vote>
 *
 * @author Dominik Eckelmann <eckelmann@cosmocode.de>
 * @author Laurent Forthomme <lforthomme.protonmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_myschulzevote_vote extends DokuWiki_Syntax_Plugin {

    function getType(){ return 'substition'; }
    function getPType(){ return 'block'; }
    function getSort(){ return 155; }

    function connectTo($mode) {
         $this->Lexer->addSpecialPattern('<svote[ ,=a-zA-Z0-9-]*?>\n.*?\n</svote>',$mode,'plugin_myschulzevote_vote');
    }

    function handle($match, $state, $pos, Doku_Handler $handler){
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

        $opts['admins'] = array();
        if (preg_match('/admins=([a-zA-Z0-9,]+)/', $lines[0], $admins)) {
            $opts['admins'] = explode(',', $admins[1]);
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

        $hlp = plugin_load('helper', 'myschulzevote');
        $hlp->createVote($candidates);

        return array('candy' => $candidates, 'opts' => $opts);
    }

    function render($mode, Doku_Renderer $renderer, $data) {

        if ($mode != 'xhtml') return false;

        if (isset($_POST['already_voted']) && checkSecurityToken()) {
            $this->_handleunvote($data);
        }
        if (isset($_POST['vote']) && checkSecurityToken()) {
            $this->_handlepost($data);
        }
        $this->_html($renderer, $data);
    }

    function _html(&$renderer, $data) {

        global $ID;

        // set alignment
        $align = $data['opts']['align'];

        $hlp = plugin_load('helper', 'myschulzevote');
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

        $form = new Doku_Form(array('id'=>'plugin__myschulzevote', 'class' => 'plugin_myschulzevote_'.$align));
        $form->startFieldset($this->getLang('cast'));
        if ($open) {
            $form->addHidden('id', $ID);
        }
        else {
            $form->addHidden('already_voted', true);
        }

        $form->addElement('<table>');
        $proposals = $this->_buildProposals($data);
        foreach ($data['candy'] as $n => $candy) {
            $form->addElement('<tr>');
            $form->addElement('<td>');
            $form->addElement($this->_render($candy));
            $form->addElement('</td>');
            if ($open) {
                $form->addElement('<td>');
                $form->addElement(form_makeListboxField('vote[' . $n . ']',
                                  $proposals,
                                  isset($_POST['vote']) ? $_POST['vote'][$n] : '',
                                  $this->_render($candy),
                                  $n,
                                  $class='block candy'));
                $form->addElement('</td>');
            }
            $form->addElement('</tr>');
        }
        $form->addElement('</table>');

        if ($open) {
            $form->addElement('<p>'.$this->getLang('howto').'</p>');
            $form->addElement(form_makeButton('submit','', $this->getLang('vote')));
            $form->addElement($this->_winnerMsg($hlp, 'leading'));
            $form->addElement('</p>');
        } else {
            $form->addElement(form_makeButton('submit', '', $this->getLang('vote_cancel')));
            $form->addElement('<p>' . $closemsg . '</p>');
        }

        $form->endFieldset();

        // if admin
        if ($this->_isInSuperUsers($data)) {
            $ranks = array();
            foreach($hlp->getRanking() as $rank => $items) {
                foreach($items as $item) {
                    $ranks[$item] = '<span class="votebar" style="width: ' . (80 / ($rank + 1)) . 'px">&nbsp;</span>';
                }
            }

            $form->startFieldset('');
            $form->addElement('<table>');
            foreach ($data['candy'] as $n => $candy) {
                $form->addElement('<tr>');
                $form->addElement('<td>');
                $form->addElement($this->_render($candy));
                $form->addElement('</td>');
                $form->addElement('<td>');
                $form->addElement($ranks[$candy]);
                $form->addElement('</td>');
                $form->addElement('</tr>');
            }
            $form->addElement('</table>');
            $form->endFieldset();
        }

        $renderer->doc .=  $form->getForm();

        return true;
    }

    function _winnerMsg($hlp, $lang) {
        $winner = $hlp->getWinner();
        return !is_null($winner) ? sprintf($this->getLang($lang), $this->_render($winner)) : '';
    }

    function _handlepost($data) {
        $err = false;
        $max_vote = null;
        foreach($_POST['vote'] as $n => &$vote) {
            if ($vote !== '') {
                if (!is_numeric($vote)) {
                    msg(sprintf($this->getLang('invalid_vote'), $data['candy'][$n]), -1);
                    $vote = '';
                    $err = true;
                } else {
                    $vote = (int) $vote;
                    $max_vote = max($vote, $max_vote);
                }
            }
        }
        unset($vote);
        if ($err || count(array_filter($_POST['vote'])) === 0) return;

        foreach($_POST['vote'] as &$vote) {
            if ($vote === '') {
                $vote = $max_vote + 1;
            }
        }

        $hlp = plugin_load('helper', 'myschulzevote');
        if (!$hlp->vote(array_combine($data['candy'], $_POST['vote']))) {
            msg($this->getLang('invalidated_vote'), -1);
            return;
        }
        msg($this->getLang('voted'), 1);
    }

    function _handleunvote($data) {
        $hlp = plugin_load('helper', 'myschulzevote');
        $hlp->deleteVote();
        msg($this->getLang('unvoted'), 1);
    }

    function _render($str) {
        return p_render('xhtml', array_slice(p_get_instructions($str), 2, -2), $notused);
    }

    function _isInSuperUsers($data) {
        global $INFO;
        foreach ($data['opts']['admins'] as $su_group)
            foreach ($INFO['userinfo']['grps'] as $user_group)
                if ($user_group === $su_group)
                    return true;
        return false;
    }

    function _buildProposals($data) {
        $candy = $data['candy'];
        $proposals = range(0, sizeof($candy));
        $proposals[0] = '-';
        if (sizeof($candy) > 0) {
            $proposals[1] = sprintf($this->getLang('first_choice'), $proposals[1]);
            $proposals[sizeof($candy)] = sprintf($this->getLang('last_choice'), $proposals[sizeof($candy)]);
        }
        return $proposals;
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
