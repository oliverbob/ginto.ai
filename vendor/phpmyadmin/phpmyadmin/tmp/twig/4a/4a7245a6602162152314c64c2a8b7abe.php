<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;

/* table/structure/display_partitions.twig */
class __TwigTemplate_18df9eb3f2fa5810b045eea344935488 extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        yield "<div id=\"partitions\">
    <fieldset class=\"pma-fieldset\">
        <legend>
            ";
yield _gettext("Partitions");
        // line 5
        yield "            ";
        yield PhpMyAdmin\Html\MySQLDocumentation::show("partitioning");
        yield "
        </legend>
        ";
        // line 7
        if (Twig\Extension\CoreExtension::testEmpty(($context["partitions"] ?? null))) {
            // line 8
            yield "            ";
            yield $this->env->getFilter('notice')->getCallable()(_gettext("No partitioning defined!"));
            yield "
        ";
        } else {
            // line 10
            yield "            <p>
                ";
yield _gettext("Partitioned by:");
            // line 12
            yield "                <code>";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["partition_method"] ?? null), "html", null, true);
            yield "(";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["partition_expression"] ?? null), "html", null, true);
            yield ")</code>
            </p>
            ";
            // line 14
            if (($context["has_sub_partitions"] ?? null)) {
                // line 15
                yield "                <p>
                    ";
yield _gettext("Sub partitioned by:");
                // line 17
                yield "                    <code>";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["sub_partition_method"] ?? null), "html", null, true);
                yield "(";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["sub_partition_expression"] ?? null), "html", null, true);
                yield ")</code>
                <p>
            ";
            }
            // line 20
            yield "            <table class=\"table table-striped table-hover table-sm\">
                <thead>
                    <tr>
                        <th colspan=\"2\">#</th>
                        <th>";
yield _gettext("Partition");
            // line 24
            yield "</th>
                        ";
            // line 25
            if (($context["has_description"] ?? null)) {
                // line 26
                yield "                            <th>";
yield _gettext("Expression");
                yield "</th>
                        ";
            }
            // line 28
            yield "                        <th>";
yield _gettext("Rows");
            yield "</th>
                        <th>";
yield _gettext("Data length");
            // line 29
            yield "</th>
                        <th>";
yield _gettext("Index length");
            // line 30
            yield "</th>
                        <th>";
yield _gettext("Comment");
            // line 31
            yield "</th>
                        <th colspan=\"";
            // line 32
            yield ((($context["range_or_list"] ?? null)) ? ("7") : ("6"));
            yield "\">
                            ";
yield _gettext("Action");
            // line 34
            yield "                        </th>
                    </tr>
                </thead>
                <tbody>
                ";
            // line 38
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(($context["partitions"] ?? null));
            foreach ($context['_seq'] as $context["_key"] => $context["partition"]) {
                // line 39
                yield "                    <tr class=\"noclick";
                yield ((($context["has_sub_partitions"] ?? null)) ? (" table-active") : (""));
                yield "\">
                        ";
                // line 40
                if (($context["has_sub_partitions"] ?? null)) {
                    // line 41
                    yield "                            <td>";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["partition"], "getOrdinal", [], "method", false, false, false, 41), "html", null, true);
                    yield "</td>
                            <td></td>
                        ";
                } else {
                    // line 44
                    yield "                            <td colspan=\"2\">";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["partition"], "getOrdinal", [], "method", false, false, false, 44), "html", null, true);
                    yield "</td>
                        ";
                }
                // line 46
                yield "                        <th>";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["partition"], "getName", [], "method", false, false, false, 46), "html", null, true);
                yield "</th>
                        ";
                // line 47
                if (($context["has_description"] ?? null)) {
                    // line 48
                    yield "                            <td>
                                <code>";
                    // line 50
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["partition"], "getExpression", [], "method", false, false, false, 50), "html", null, true);
                    // line 51
                    yield (((CoreExtension::getAttribute($this->env, $this->source, $context["partition"], "getMethod", [], "method", false, false, false, 51) == "LIST")) ? (" IN (") : (" < "));
                    // line 52
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["partition"], "getDescription", [], "method", false, false, false, 52), "html", null, true);
                    // line 53
                    yield (((CoreExtension::getAttribute($this->env, $this->source, $context["partition"], "getMethod", [], "method", false, false, false, 53) == "LIST")) ? (")") : (""));
                    // line 54
                    yield "</code>
                            </td>
                        ";
                }
                // line 57
                yield "                        <td class=\"value\">";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["partition"], "getRows", [], "method", false, false, false, 57), "html", null, true);
                yield "</td>
                        <td class=\"value\">
                            ";
                // line 59
                $context["data_length"] = PhpMyAdmin\Util::formatByteDown(CoreExtension::getAttribute($this->env, $this->source,                 // line 60
$context["partition"], "getDataLength", [], "method", false, false, false, 60), 3, 1);
                // line 64
                yield "                            <span>";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_0 = ($context["data_length"] ?? null)) && is_array($__internal_compile_0) || $__internal_compile_0 instanceof ArrayAccess ? ($__internal_compile_0[0] ?? null) : null), "html", null, true);
                yield "</span>
                            <span class=\"unit\">";
                // line 65
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_1 = ($context["data_length"] ?? null)) && is_array($__internal_compile_1) || $__internal_compile_1 instanceof ArrayAccess ? ($__internal_compile_1[1] ?? null) : null), "html", null, true);
                yield "</span>
                        </td>
                        <td class=\"value\">
                            ";
                // line 68
                $context["index_length"] = PhpMyAdmin\Util::formatByteDown(CoreExtension::getAttribute($this->env, $this->source,                 // line 69
$context["partition"], "getIndexLength", [], "method", false, false, false, 69), 3, 1);
                // line 73
                yield "                            <span>";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_2 = ($context["index_length"] ?? null)) && is_array($__internal_compile_2) || $__internal_compile_2 instanceof ArrayAccess ? ($__internal_compile_2[0] ?? null) : null), "html", null, true);
                yield "</span>
                            <span class=\"unit\">";
                // line 74
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_3 = ($context["index_length"] ?? null)) && is_array($__internal_compile_3) || $__internal_compile_3 instanceof ArrayAccess ? ($__internal_compile_3[1] ?? null) : null), "html", null, true);
                yield "</span>
                        </td>
                        <td>";
                // line 76
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["partition"], "getComment", [], "method", false, false, false, 76), "html", null, true);
                yield "</td>

                        <td>
                          <a id=\"partition_action_ANALYZE\" class=\"ajax\" href=\"";
                // line 79
                yield PhpMyAdmin\Url::getFromRoute("/table/partition/analyze");
                yield "\" data-post=\"";
                yield PhpMyAdmin\Url::getCommon(["db" =>                 // line 80
($context["db"] ?? null), "table" =>                 // line 81
($context["table"] ?? null), "partition_name" => CoreExtension::getAttribute($this->env, $this->source,                 // line 82
$context["partition"], "getName", [], "method", false, false, false, 82)], "", false);
                // line 83
                yield "\">
                            ";
                // line 84
                yield PhpMyAdmin\Html\Generator::getIcon("b_search", _gettext("Analyze"));
                yield "
                          </a>
                        </td>

                        <td>
                          <a id=\"partition_action_CHECK\" class=\"ajax\" href=\"";
                // line 89
                yield PhpMyAdmin\Url::getFromRoute("/table/partition/check");
                yield "\" data-post=\"";
                yield PhpMyAdmin\Url::getCommon(["db" =>                 // line 90
($context["db"] ?? null), "table" =>                 // line 91
($context["table"] ?? null), "partition_name" => CoreExtension::getAttribute($this->env, $this->source,                 // line 92
$context["partition"], "getName", [], "method", false, false, false, 92)], "", false);
                // line 93
                yield "\">
                            ";
                // line 94
                yield PhpMyAdmin\Html\Generator::getIcon("eye", _gettext("Check"));
                yield "
                          </a>
                        </td>

                        <td>
                          <a id=\"partition_action_OPTIMIZE\" class=\"ajax\" href=\"";
                // line 99
                yield PhpMyAdmin\Url::getFromRoute("/table/partition/optimize");
                yield "\" data-post=\"";
                yield PhpMyAdmin\Url::getCommon(["db" =>                 // line 100
($context["db"] ?? null), "table" =>                 // line 101
($context["table"] ?? null), "partition_name" => CoreExtension::getAttribute($this->env, $this->source,                 // line 102
$context["partition"], "getName", [], "method", false, false, false, 102)], "", false);
                // line 103
                yield "\">
                            ";
                // line 104
                yield PhpMyAdmin\Html\Generator::getIcon("normalize", _gettext("Optimize"));
                yield "
                          </a>
                        </td>

                        <td>
                          <a id=\"partition_action_REBUILD\" class=\"ajax\" href=\"";
                // line 109
                yield PhpMyAdmin\Url::getFromRoute("/table/partition/rebuild");
                yield "\" data-post=\"";
                yield PhpMyAdmin\Url::getCommon(["db" =>                 // line 110
($context["db"] ?? null), "table" =>                 // line 111
($context["table"] ?? null), "partition_name" => CoreExtension::getAttribute($this->env, $this->source,                 // line 112
$context["partition"], "getName", [], "method", false, false, false, 112)], "", false);
                // line 113
                yield "\">
                            ";
                // line 114
                yield PhpMyAdmin\Html\Generator::getIcon("s_tbl", _gettext("Rebuild"));
                yield "
                          </a>
                        </td>

                        <td>
                          <a id=\"partition_action_REPAIR\" class=\"ajax\" href=\"";
                // line 119
                yield PhpMyAdmin\Url::getFromRoute("/table/partition/repair");
                yield "\" data-post=\"";
                yield PhpMyAdmin\Url::getCommon(["db" =>                 // line 120
($context["db"] ?? null), "table" =>                 // line 121
($context["table"] ?? null), "partition_name" => CoreExtension::getAttribute($this->env, $this->source,                 // line 122
$context["partition"], "getName", [], "method", false, false, false, 122)], "", false);
                // line 123
                yield "\">
                            ";
                // line 124
                yield PhpMyAdmin\Html\Generator::getIcon("b_tblops", _gettext("Repair"));
                yield "
                          </a>
                        </td>

                        <td>
                          <a id=\"partition_action_TRUNCATE\" class=\"ajax\" href=\"";
                // line 129
                yield PhpMyAdmin\Url::getFromRoute("/table/partition/truncate");
                yield "\" data-post=\"";
                yield PhpMyAdmin\Url::getCommon(["db" =>                 // line 130
($context["db"] ?? null), "table" =>                 // line 131
($context["table"] ?? null), "partition_name" => CoreExtension::getAttribute($this->env, $this->source,                 // line 132
$context["partition"], "getName", [], "method", false, false, false, 132)], "", false);
                // line 133
                yield "\">
                            ";
                // line 134
                yield PhpMyAdmin\Html\Generator::getIcon("b_empty", _gettext("Truncate"));
                yield "
                          </a>
                        </td>

                        ";
                // line 138
                if (($context["range_or_list"] ?? null)) {
                    // line 139
                    yield "                          <td>
                            <a id=\"partition_action_DROP\" class=\"ajax\" href=\"";
                    // line 140
                    yield PhpMyAdmin\Url::getFromRoute("/table/partition/drop");
                    yield "\" data-post=\"";
                    yield PhpMyAdmin\Url::getCommon(["db" =>                     // line 141
($context["db"] ?? null), "table" =>                     // line 142
($context["table"] ?? null), "partition_name" => CoreExtension::getAttribute($this->env, $this->source,                     // line 143
$context["partition"], "getName", [], "method", false, false, false, 143)], "", false);
                    // line 144
                    yield "\">
                              ";
                    // line 145
                    yield PhpMyAdmin\Html\Generator::getIcon("b_drop", _gettext("Drop"));
                    yield "
                            </a>
                          </td>
                        ";
                }
                // line 149
                yield "
                        ";
                // line 150
                if (($context["has_sub_partitions"] ?? null)) {
                    // line 151
                    yield "                            ";
                    $context['_parent'] = $context;
                    $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, $context["partition"], "getSubPartitions", [], "method", false, false, false, 151));
                    foreach ($context['_seq'] as $context["_key"] => $context["sub_partition"]) {
                        // line 152
                        yield "                                <tr class=\"noclick\">
                                    <td></td>
                                    <td>";
                        // line 154
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["sub_partition"], "getOrdinal", [], "method", false, false, false, 154), "html", null, true);
                        yield "</td>
                                    <td>";
                        // line 155
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["sub_partition"], "getName", [], "method", false, false, false, 155), "html", null, true);
                        yield "</td>
                                    ";
                        // line 156
                        if (($context["has_description"] ?? null)) {
                            // line 157
                            yield "                                        <td></td>
                                    ";
                        }
                        // line 159
                        yield "                                    <td class=\"value\">";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["sub_partition"], "getRows", [], "method", false, false, false, 159), "html", null, true);
                        yield "</td>
                                    <td class=\"value\">
                                        ";
                        // line 161
                        $context["data_length"] = PhpMyAdmin\Util::formatByteDown(CoreExtension::getAttribute($this->env, $this->source,                         // line 162
$context["sub_partition"], "getDataLength", [], "method", false, false, false, 162), 3, 1);
                        // line 166
                        yield "                                        <span>";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_4 = ($context["data_length"] ?? null)) && is_array($__internal_compile_4) || $__internal_compile_4 instanceof ArrayAccess ? ($__internal_compile_4[0] ?? null) : null), "html", null, true);
                        yield "</span>
                                        <span class=\"unit\">";
                        // line 167
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_5 = ($context["data_length"] ?? null)) && is_array($__internal_compile_5) || $__internal_compile_5 instanceof ArrayAccess ? ($__internal_compile_5[1] ?? null) : null), "html", null, true);
                        yield "</span>
                                    </td>
                                    <td class=\"value\">
                                        ";
                        // line 170
                        $context["index_length"] = PhpMyAdmin\Util::formatByteDown(CoreExtension::getAttribute($this->env, $this->source,                         // line 171
$context["sub_partition"], "getIndexLength", [], "method", false, false, false, 171), 3, 1);
                        // line 175
                        yield "                                        <span>";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_6 = ($context["index_length"] ?? null)) && is_array($__internal_compile_6) || $__internal_compile_6 instanceof ArrayAccess ? ($__internal_compile_6[0] ?? null) : null), "html", null, true);
                        yield "</span>
                                        <span class=\"unit\">";
                        // line 176
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_7 = ($context["index_length"] ?? null)) && is_array($__internal_compile_7) || $__internal_compile_7 instanceof ArrayAccess ? ($__internal_compile_7[1] ?? null) : null), "html", null, true);
                        yield "</span>
                                    </td>
                                    <td>";
                        // line 178
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["sub_partition"], "getComment", [], "method", false, false, false, 178), "html", null, true);
                        yield "</td>
                                    <td colspan=\"";
                        // line 179
                        yield ((($context["range_or_list"] ?? null)) ? ("7") : ("6"));
                        yield "\"></td>
                                </tr>
                            ";
                    }
                    $_parent = $context['_parent'];
                    unset($context['_seq'], $context['_iterated'], $context['_key'], $context['sub_partition'], $context['_parent'], $context['loop']);
                    $context = array_intersect_key($context, $_parent) + $_parent;
                    // line 182
                    yield "                        ";
                }
                // line 183
                yield "                    </tr>
                ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['partition'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 185
            yield "                </tbody>
            </table>
        ";
        }
        // line 188
        yield "    </fieldset>
    <fieldset class=\"pma-fieldset tblFooters d-print-none\">
        <form action=\"";
        // line 190
        yield PhpMyAdmin\Url::getFromRoute("/table/structure/partitioning");
        yield "\" method=\"post\">
            ";
        // line 191
        yield PhpMyAdmin\Url::getHiddenInputs(($context["db"] ?? null), ($context["table"] ?? null));
        yield "

            ";
        // line 193
        if (Twig\Extension\CoreExtension::testEmpty(($context["partitions"] ?? null))) {
            // line 194
            yield "                <input class=\"btn btn-secondary\" type=\"submit\" value=\"";
yield _gettext("Partition table");
            yield "\">
            ";
        } else {
            // line 196
            yield "                ";
            yield PhpMyAdmin\Html\Generator::linkOrButton(PhpMyAdmin\Url::getFromRoute("/sql"), ["db" =>             // line 199
($context["db"] ?? null), "table" =>             // line 200
($context["table"] ?? null), "sql_query" => (("ALTER TABLE " . PhpMyAdmin\Util::backquote(            // line 201
($context["table"] ?? null))) . " REMOVE PARTITIONING")], _gettext("Remove partitioning"), ["class" => "btn btn-secondary ajax", "id" => "remove_partitioning"]);
            // line 206
            yield "
                <input class=\"btn btn-secondary\" type=\"submit\" value=\"";
yield _gettext("Edit partitioning");
            // line 207
            yield "\">
            ";
        }
        // line 209
        yield "        </form>
    </fieldset>
</div>
";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "table/structure/display_partitions.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable()
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo()
    {
        return array (  470 => 209,  466 => 207,  462 => 206,  460 => 201,  459 => 200,  458 => 199,  456 => 196,  450 => 194,  448 => 193,  443 => 191,  439 => 190,  435 => 188,  430 => 185,  423 => 183,  420 => 182,  411 => 179,  407 => 178,  402 => 176,  397 => 175,  395 => 171,  394 => 170,  388 => 167,  383 => 166,  381 => 162,  380 => 161,  374 => 159,  370 => 157,  368 => 156,  364 => 155,  360 => 154,  356 => 152,  351 => 151,  349 => 150,  346 => 149,  339 => 145,  336 => 144,  334 => 143,  333 => 142,  332 => 141,  329 => 140,  326 => 139,  324 => 138,  317 => 134,  314 => 133,  312 => 132,  311 => 131,  310 => 130,  307 => 129,  299 => 124,  296 => 123,  294 => 122,  293 => 121,  292 => 120,  289 => 119,  281 => 114,  278 => 113,  276 => 112,  275 => 111,  274 => 110,  271 => 109,  263 => 104,  260 => 103,  258 => 102,  257 => 101,  256 => 100,  253 => 99,  245 => 94,  242 => 93,  240 => 92,  239 => 91,  238 => 90,  235 => 89,  227 => 84,  224 => 83,  222 => 82,  221 => 81,  220 => 80,  217 => 79,  211 => 76,  206 => 74,  201 => 73,  199 => 69,  198 => 68,  192 => 65,  187 => 64,  185 => 60,  184 => 59,  178 => 57,  173 => 54,  171 => 53,  169 => 52,  167 => 51,  165 => 50,  162 => 48,  160 => 47,  155 => 46,  149 => 44,  142 => 41,  140 => 40,  135 => 39,  131 => 38,  125 => 34,  120 => 32,  117 => 31,  113 => 30,  109 => 29,  103 => 28,  97 => 26,  95 => 25,  92 => 24,  85 => 20,  76 => 17,  72 => 15,  70 => 14,  62 => 12,  58 => 10,  52 => 8,  50 => 7,  44 => 5,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "table/structure/display_partitions.twig", "/home/oliverbob/ginto/vendor/phpmyadmin/phpmyadmin/templates/table/structure/display_partitions.twig");
    }
}
