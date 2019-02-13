<?php

function xml_unserialize(&$xml, $isnormal = FALSE) {
    $xml_parser = new XML($isnormal);
    $data = $xml_parser->parse($xml);
    $xml_parser->destruct();
    return $data;
}

function xml_serialize($arr, $htmlon = FALSE, $isnormal = FALSE, $level = 1) {
    $s = $level == 1 ? "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n".(count($arr)>1 ? '<hash>' : '') : '';
    $space = str_repeat("\t", $level);
    foreach($arr as $k => $v) {
        if($k === 'api_xml_list_node')
            continue;

        if(is_numeric($k)) {
            if($arr['api_xml_list_node'] && !is_numeric($arr['api_xml_list_node'])) {
                $node = $arr['api_xml_list_node'];
            } else {
                $node = 'item';
            }
        } else {
            $node = $k;
        }

        if(!is_array($v)) {
            $s .= $space."<$node>".($htmlon ? '<![CDATA[' : '').$v.($htmlon ? ']]>' : '')."</$node>\r\n";
        } else {
            $s .= $space."<$node>\r\n".xml_serialize($v, $htmlon, $isnormal, $level + 1).$space."</$node>\r\n";
        }
    }
    $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
    return $level == 1 ? $s.(count($arr)>1 ? '</hash>' : '') : $s;
}

class XML {

    var $parser;
    var $document;
    var $stack;
    var $data;
    var $last_opened_tag;
    var $isnormal;
    var $attrs = array();
    var $failed = FALSE;

    function __construct($isnormal) {
        $this->XML($isnormal);
    }

    function XML($isnormal) {
        $this->isnormal = $isnormal;
        $this->parser = xml_parser_create('UTF-8');
        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);
        xml_set_object($this->parser, $this);
        xml_set_element_handler($this->parser, 'open','close');
        xml_set_character_data_handler($this->parser, 'data');
    }

    function destruct() {
        xml_parser_free($this->parser);
    }

    function parse(&$data) {
        $this->document = array();
        $this->stack    = array();
        return xml_parse($this->parser, $data, true) && !$this->failed ? $this->document : '';
    }

    function open(&$parser, $tag, $attributes) {
        $this->data = '';
        $this->failed = FALSE;
        if(!$this->isnormal) {
            if(isset($attributes['id']) && !is_string($this->document[$attributes['id']])) {
                $this->document  = &$this->document[$attributes['id']];
            } else {
                $this->failed = TRUE;
            }
        } else {
            if(!isset($this->document[$tag]) || !is_string($this->document[$tag])) {
                $this->document  = &$this->document[$tag];
            } else {
                $this->failed = TRUE;
            }
        }
        $this->stack[] = &$this->document;
        $this->last_opened_tag = $tag;
        $this->attrs = $attributes;
    }

    function data(&$parser, $data) {
        if($this->last_opened_tag != NULL) {
            $this->data .= $data;
        }
    }

    function close(&$parser, $tag) {
        if($this->last_opened_tag == $tag) {
            $this->document = $this->data;
            $this->last_opened_tag = NULL;
        }
        array_pop($this->stack);
        if($this->stack) {
            $this->document = &$this->stack[count($this->stack)-1];
        }
    }

}
