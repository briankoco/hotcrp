<?php
// src/pages/p_formulagraph.php -- HotCRP review preference graph drawing page
// Copyright (c) 2006-2021 Eddie Kohler; see LICENSE.

class FormulaGraph_Page {
    /** @var Conf */
    public $conf;
    /** @var Contact */
    public $user;
    /** @var Qrequest */
    public $qreq;
    /** @var list<string> */
    public $queries;
    /** @var list<string> */
    public $styles;

    function __construct(Contact $user, Qrequest $qreq) {
        $this->conf = $user->conf;
        $this->user = $user;
        $this->qreq = $qreq;
    }

    /** @param int|string $i
     * @return string */
    private function formulas_qrow($i, $q, $s, $status) {
        if ($q === "all") {
            $q = "";
        }
        $klass = MessageSet::status_class($status, "need-suggest papersearch want-focus");
        return '<tr><td class="lentry">'
            . Ht::entry("q$i", $q, ["size" => 40, "placeholder" => "(All)", "class" => $klass, "id" => "q$i", "spellcheck" => false, "autocomplete" => "off", "aria-label" => "Search"])
            . " <span class=\"pl-3\">Style:</span> &nbsp;"
            . Ht::select("s$i", ["default" => "default", "plain" => "plain", "redtag" => "red", "orangetag" => "orange", "yellowtag" => "yellow", "greentag" => "green", "bluetag" => "blue", "purpletag" => "purple", "graytag" => "gray"], $s !== "" ? $s : "by-tag")
            . ' <span class="nb btnbox aumovebox ml-3"><a href="" class="ui btn row-order-ui moveup" tabindex="-1">'
            . Icons::ui_triangle(0)
            . '</a><a href="" class="ui btn row-order-ui movedown" tabindex="-1">'
            . Icons::ui_triangle(2)
            . '</a><a href="" class="ui btn row-order-ui delete" tabindex="-1">✖</a></span></td></tr>';
    }

    /** @param FormulaGraph $fg
     * @param list<string> $queries
     * @param list<string> $styles */
    private function render_graph($fg, $queries, $styles) {
        for ($i = 0; $i < count($queries); ++$i) {
            $fg->add_query($queries[$i], $styles[$i], "q$i");
        }

        if ($fg->has_messages()) {
            echo Ht::msg($fg->message_texts(), $fg->problem_status());
        }

        $xhtml = htmlspecialchars($fg->fx_expression());
        if ($fg->fx_format() === Fexpr::FTAG) {
            $xhtml = "tag";
        }

        if ($fg->fx_format() === Fexpr::FSEARCH) {
            $h2 = "";
        } else if ($fg->type === FormulaGraph::RAWCDF) {
            $h2 = "Cumulative count of $xhtml";
        } else if ($fg->type & FormulaGraph::CDF) {
            $h2 = "$xhtml CDF";
        } else if (($fg->type & FormulaGraph::BARCHART)
                   && $fg->fy->expression === "sum(1)") {
            $h2 = $xhtml;
        } else if ($fg->type & FormulaGraph::BARCHART) {
            $h2 = htmlspecialchars($fg->fy->expression) . " by $xhtml";
        } else {
            $h2 = htmlspecialchars($fg->fy->expression) . " vs. $xhtml";
        }
        $highlightable = ($fg->type & (FormulaGraph::SCATTER | FormulaGraph::BOXPLOT))
            && $fg->fx_combinable();

        $attr = [];
        if (!($fg->type & FormulaGraph::CDF)) {
            $attr["data-graph-fx"] = $fg->fx->expression;
            $attr["data-graph-fy"] = $fg->fy->expression;
        }
        Graph_Page::echo_graph($highlightable, $h2, $attr);

        echo Ht::unstash(), Ht::script_open(),
            '$(function () { hotcrp.graph("#hotgraph", ',
            json_encode_browser($fg->graph_json()),
            ") });</script>\n";
    }

    /** @param MessageSet $fgm
     * @param list<string> $queries
     * @param list<string> $styles */
    private function render_ui($fgm, $queries, $styles) {
        echo Ht::form($this->conf->hoturl("graph", "group=formula"), ["method" => "get"]);
        /*echo '<div>',
            Ht::button(Icons::ui_graph_scatter(), ["class" => "btn-t"]),
            Ht::button(Icons::ui_graph_bars(), ["class" => "btn-t"]),
            Ht::button(Icons::ui_graph_box(), ["class" => "btn-t"]),
            Ht::button(Icons::ui_graph_cdf(), ["class" => "btn-t"]),
            '</div>';*/

        // X axis
        echo '<div class="f-mcol">',
            '<div class="', $fgm->control_class("fx", "f-i maxw-480"), '">',
            '<label for="x_entry">X axis</label>',
            Ht::entry("x", (string) $this->qreq->x, ["id" => "x_entry", "size" => 32, "class" => "w-99"]),
            '<div class="f-h"><a href="', $this->conf->hoturl("help", "t=formulas"), '">Formula</a> or “search”</div>',
            '</div>';
        // Y axis
        echo '<div class="', $fgm->control_class("fy", "f-i maxw-480"), '">',
            '<label for="y_entry">Y axis</label>',
            Ht::entry("y", (string) $this->qreq->y, ["id" => "y_entry", "size" => 32, "class" => "w-99"]),
            '<div class="f-h"><a href="', $this->conf->hoturl("help", "t=formulas"), '">Formula</a> or “cdf”, “count”, “fraction”, “box <em>formula</em>”, “bar <em>formula</em>”</div>',
            '</div>',
            '</div>';
        // Series
        echo '<div class="', $fgm->control_class("q1", "f-i"), '">',
            '<label for="q1">Data sets</label>',
            '<table class="js-row-order"><tbody id="qcontainer" data-row-template="',
            htmlspecialchars($this->formulas_qrow('$', "", "by-tag", 0)), '">';
        for ($i = 0; $i < count($styles); ++$i) {
            echo $this->formulas_qrow($i + 1, $queries[$i], $styles[$i],
                                      $fgm->problem_status_at("q$i"));
        }
        echo "</tbody><tbody><tr><td>",
            Ht::button("Add data set", ["class" => "ui row-order-ui addrow"]),
            "</td></tr></tbody></table></div>\n",
            Ht::submit("Graph", ["class" => 'btn btn-primary']), '</form>';
    }

    static function go(Contact $user, Qrequest $qreq, $gx, $gj) {
        (new FormulaGraph_Page($user, $qreq))->render($gj);
    }

    function render($gj) {
        // parse arguments
        $qreq = $this->qreq;
        if (!isset($qreq->x) || !isset($qreq->y)) {
            // derive a sample graph
            $fields = $this->conf->review_form()->example_fields($this->user);
            unset($qreq->x, $qreq->y);
            if (count($fields) > 0) {
                $qreq->y = "avg(" . $fields[0]->search_keyword() . ")";
            }
            if (count($fields) > 1) {
                $qreq->x = "avg(" . $fields[1]->search_keyword() . ")";
            } else {
                $qreq->x = "pid";
            }
        }
        list($queries, $styles) = FormulaGraph::parse_queries($qreq);

        // create graph
        if ($qreq->x && ($qreq->gtype || $qreq->y)) {
            $fg = new FormulaGraph($this->user, $qreq->gtype, $qreq->x, $qreq->y);
            if ($qreq->xorder) {
                $fg->set_xorder($qreq->xorder);
            }
            $this->render_graph($fg, $queries, $styles);
            $this->render_ui($fg, $queries, $styles);
        } else {
            $this->render_ui(new MessageSet, $queries, $styles);
        }
    }
}
