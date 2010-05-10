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
        $opts['align'] = null;
        if (preg_match('/(left|right|center)/',$lines[0], $align)) {
            $opts['align'] = $align[0];
        }

        unset($lines[count($lines)-1]);
        unset($lines[0]);

        $candidates = array();

        foreach ($lines as $line) {
            if (!empty($line)) {
                $candidates[] = $line;
            }
        }

        return array('candy' => $candidates, 'opts' => $opts);
    }

    function render($mode, &$renderer, $data) {

        if ($mode != 'xhtml') return false;
        global $ID;

        // set alignment
        $align = "";
        if ($data['opts']['align'] !== null) {
            $align = ' ' . $data['opts']['align'];
        }

        // check if the vote is over.
        $open = true;
        if ($data['opts']['date'] !== null) {
            if ($data['opts']['date'] < time()) {
                $open = false;
            }
        }



        $renderer->doc .= '<div class="plugin_schulzevote_vote' .$align. '">';
        if ($open) $renderer->doc .= '<form action="'.wl($ID).'" method="post">';

        if ($open) foreach ($data['candy'] as $candy) {
            $name = hsc($candy);
            $renderer->doc .= '  <p>';
            $renderer->doc .= '    <input id="plugin__schulzevote__'.$name.'" type="text" name="vote['.$name.']" class="edit" />';
            $renderer->doc .= '    <label for="plugin__schulzevote__'.$name.'">'.$name.'</label>';
            $renderer->doc .= '  </p>';
        }
        $renderer->doc .= '  <p>';
        if ($open) $renderer->doc .= '<input type="submit" value="Vote!" class="button" />';
        else $renderer->doc .= 'Vote is over';
        $renderer->doc .= '</p>';
        if ($open) $renderer->doc .= '</form>';
        $renderer->doc .= '</div>';


        return true;
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :


