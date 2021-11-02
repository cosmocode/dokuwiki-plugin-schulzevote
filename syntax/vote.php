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

class syntax_plugin_schulzevote_vote extends DokuWiki_Syntax_Plugin {

    function getType(){ return 'substition'; }
    function getPType(){ return 'block'; }
    function getSort(){ return 155; }

    function connectTo($mode) {
         $this->Lexer->addSpecialPattern('<vote\b.*?>\n.*?\n</vote>',$mode,'plugin_schulzevote_vote');
    }

    function handle($match, $state, $pos, Doku_Handler $handler){
        $lines = explode("\n", $match);

        $opts = array();
        $opts['hide_results'] = false;
        if (preg_match('/ hideResults/', $lines[0], $hide_results)) {
            $opts['hide_results'] = true;
        }

        // Determine date from syntax
        $opts['date'] = null;

        if (preg_match('/ \d{4}-\d{2}-\d{2}/', $lines[0], $opts['date'])) {

            $opts['date'] = strtotime($opts['date'][0]);
            if ($opts['date'] === false || $opts['date'] === -1) {
                $opts['date'] = null;
            }
        }

        $opts['admin_users'] = array();
        if (preg_match('/ adminUsers=([a-zA-Z0-9,]+)/', $lines[0], $admins)) {
            $opts['admin_users'] = explode(',', $admins[1]);
        }

        $opts['admin_groups'] = array();
        if (preg_match('/ adminGroups=([a-zA-Z0-9,]+)/', $lines[0], $admins)) {
            $opts['admin_groups'] = explode(',', $admins[1]);
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

    function render($mode, Doku_Renderer $renderer, $data) {

        if ($mode != 'xhtml') return false;

        global $INPUT;

        if ($INPUT->post->int('vote_cancel') && checkSecurityToken()) {
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

        $hlp = plugin_load('helper', 'schulzevote');
#        dbg($hlp);

        // check if the vote is over or outdated.
        $open = ($data['opts']['date'] !== null) && ($data['opts']['date'] > time());
        if ($hlp->outdated) {
            $open = false;
            msg($this->getLang('outdated_poll'), 0);
        }
        if ($open) {
            $renderer->info['cache'] = false;
            if (!isset($_SERVER['REMOTE_USER'])) {
                $open = false;
                $closemsg = $this->getLang('no_remote_user');
            } elseif ($hlp->hasVoted()) {
                $open = false;
                $closemsg = $this->getLang('already_voted');
            }
        } else {
            $closemsg = $this->getLang('vote_over').'<br />'.
                        $this->_winnerMsg($hlp, 'has_won');
        }

        if ($open) {
            $form = new dokuwiki\Form\Form(array('id'=>'plugin__schulzevote', 'class' => 'plugin_schulzevote_'.$align));
            $form->addFieldsetOpen($this->getLang('cast'));
            $form->setHiddenField('id', $ID);
            $form->addTagOpen('table');
            $proposals = $this->_buildProposals($data);
            foreach ($data['candy'] as $n => $candy) {
                $form->addTagOpen('tr');
                $form->addTagOpen('td');
                $form->addLabel($this->_render($candy));
                $form->addTagClose('td');
                if ($open) {
                    $form->addTagOpen('td');
                    $form->addDropdown('vote[' . $n . ']', $proposals)->addClass('plugin__schulzevote__vote_selector');
                    $form->addTagClose('td');
                }
                $form->addTagClose('tr');
            }
            $form->addTagClose('table');

            if (!$hlp->hasVoted()) {
                $form->addHTML('<p>'.$this->getLang('howto').'</p>');
                $form->addButton('submit', $this->getLang('vote'));
            } else {
                $form->addButton('vote_cancel', $this->getLang('vote_cancel'));
                $form->addHTML('<p>' . $closemsg . '</p>');
            }

            $form->addFieldsetClose();
            $renderer->doc .=  $form->toHTML();
        }

        // if admin or results not hidden
        if (!$data['opts']['hide_results'] || $this->_isInSuperUsers($data)) {
            $form = new dokuwiki\Form\Form(array('class' => 'plugin_schulzevote_'.$align));
            $ranks = array();
            foreach($hlp->getRanking() as $rank => $items) {
                foreach($items as $item) {
                    $ranks[$item] = '<span class="votebar" style="width: ' . (80 / ($rank + 1)) . 'px">&nbsp;</span>';
                }
            }

            $form->addFieldsetOpen($this->getLang('intermediate_results'));
            $form->addHTML('<p>' . $this->_winnerMsg($hlp, 'leading') . '</p>');
            $form->addTagOpen('table');
            foreach ($data['candy'] as $n => $candy) {
                $form->addTagOpen('tr');
                $form->addTagOpen('td');
                $form->addLabel($this->_render($candy));
                $form->addTagClose('td');
                $form->addTagOpen('td');
                $form->addHTML($ranks[$candy]);
                $form->addTagClose('td');
                $form->addTagClose('tr');
            }
            $form->addTagClose('table');
            $form->addFieldsetClose();
            $renderer->doc .=  $form->toHTML();
        }

        return true;
    }

    function _winnerMsg($hlp, $lang) {
        $winner = $hlp->getWinner();
        return !is_null($winner) ? sprintf($this->getLang($lang), $this->_render($winner)) : '';
    }

    function _handlepost($data) {
        $err = false;
        $err_str = "";
        $max_vote = null;
        foreach($_POST['vote'] as $n => &$vote) {
            if ($vote !== '') {
                $vote = explode(' ', $vote)[0];
                if (!is_numeric($vote)) {
                    $err_str .= "<li>" . $this->render_text(sprintf($this->getLang('invalid_vote'), $data['candy'][$n]), 'xhtml') . "</li>";
                    $vote = '';
                    $err = true;
                } else {
                    $vote = (int) $vote;
                    $max_vote = max($vote, $max_vote);
                }
            }
        }
        unset($vote);
        if ($err_str != "")
            msg(sprintf($this->getLang('error_found'), $err_str), -1);
        if ($err || count(array_filter($_POST['vote'])) === 0) return;

        foreach($_POST['vote'] as &$vote) {
            if ($vote === '') {
                $vote = $max_vote + 1;
            }
        }

        $hlp = plugin_load('helper', 'schulzevote');
        if (!$hlp->vote(array_combine($data['candy'], $_POST['vote']))) {
            msg($this->getLang('invalidated_vote'), -1);
            return;
        }
        msg($this->getLang('voted'), 1);
    }

    function _handleunvote($data) {
        $hlp = plugin_load('helper', 'schulzevote');
        $hlp->deleteVote();
        msg($this->getLang('unvoted'), 1);
    }

    function _render($str) {
        return p_render('xhtml', array_slice(p_get_instructions($str), 2, -2), $notused);
    }

    function _isInSuperUsers($data) {
        global $INFO;

        if (!isset($data['opts']['admin_users']) || !isset($data['opts']['admin_groups']))
            return false; // ensure backward-compatibility with former polls
        foreach ($data['opts']['admin_users'] as $su_user)
            if ($_SERVER['REMOTE_USER'] === $su_user)
                return true;
        foreach ($data['opts']['admin_groups'] as $su_group)
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
