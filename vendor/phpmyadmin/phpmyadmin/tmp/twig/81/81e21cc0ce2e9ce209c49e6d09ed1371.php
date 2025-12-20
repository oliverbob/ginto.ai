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

/* table/structure/display_table_stats.twig */
class __TwigTemplate_85d90bc3991c430f0c1f83d5f51617b9 extends Template
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
        yield "<div id=\"tablestatistics\">
    <fieldset class=\"pma-fieldset\">
        <legend>";
yield _gettext("Information");
        // line 3
        yield "</legend>
        ";
        // line 4
        if ((($__internal_compile_0 = ($context["showtable"] ?? null)) && is_array($__internal_compile_0) || $__internal_compile_0 instanceof ArrayAccess ? ($__internal_compile_0["TABLE_COMMENT"] ?? null) : null)) {
            // line 5
            yield "            <p>
                <strong>";
yield _gettext("Table comments:");
            // line 6
            yield "</strong>
                ";
            // line 7
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_1 = ($context["showtable"] ?? null)) && is_array($__internal_compile_1) || $__internal_compile_1 instanceof ArrayAccess ? ($__internal_compile_1["TABLE_COMMENT"] ?? null) : null), "html", null, true);
            yield "
            </p>
        ";
        }
        // line 10
        yield "        <a id=\"showusage\"></a>

        ";
        // line 12
        if (( !($context["tbl_is_view"] ?? null) &&  !($context["db_is_system_schema"] ?? null))) {
            // line 13
            yield "            <table class=\"table table-striped table-hover table-sm w-auto caption-top\">
                <caption>";
yield _gettext("Space usage");
            // line 14
            yield "</caption>
                <tbody>
                    <tr>
                        <th class=\"name\">";
yield _gettext("Data");
            // line 17
            yield "</th>
                        <td class=\"value\">";
            // line 18
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["data_size"] ?? null), "html", null, true);
            yield "</td>
                        <td class=\"unit\">";
            // line 19
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["data_unit"] ?? null), "html", null, true);
            yield "</td>
                    </tr>

                ";
            // line 22
            if (array_key_exists("index_size", $context)) {
                // line 23
                yield "                    <tr>
                        <th class=\"name\">";
yield _gettext("Index");
                // line 24
                yield "</th>
                        <td class=\"value\">";
                // line 25
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["index_size"] ?? null), "html", null, true);
                yield "</td>
                        <td class=\"unit\">";
                // line 26
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["index_unit"] ?? null), "html", null, true);
                yield "</td>
                    </tr>
                ";
            }
            // line 29
            yield "
                ";
            // line 30
            if (array_key_exists("free_size", $context)) {
                // line 31
                yield "                    <tr>
                        <th class=\"name\">";
yield _gettext("Overhead");
                // line 32
                yield "</th>
                        <td class=\"value\">";
                // line 33
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["free_size"] ?? null), "html", null, true);
                yield "</td>
                        <td class=\"unit\">";
                // line 34
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["free_unit"] ?? null), "html", null, true);
                yield "</td>
                    </tr>
                    <tr>
                        <th class=\"name\">";
yield _gettext("Effective");
                // line 37
                yield "</th>
                        <td class=\"value\">";
                // line 38
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["effect_size"] ?? null), "html", null, true);
                yield "</td>
                        <td class=\"unit\">";
                // line 39
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["effect_unit"] ?? null), "html", null, true);
                yield "</td>
                    </tr>
                ";
            }
            // line 42
            yield "
                ";
            // line 43
            if ((array_key_exists("tot_size", $context) && (($context["mergetable"] ?? null) == false))) {
                // line 44
                yield "                    <tr>
                        <th class=\"name\">";
yield _gettext("Total");
                // line 45
                yield "</th>
                        <td class=\"value\">";
                // line 46
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["tot_size"] ?? null), "html", null, true);
                yield "</td>
                        <td class=\"unit\">";
                // line 47
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["tot_unit"] ?? null), "html", null, true);
                yield "</td>
                    </tr>
                ";
            }
            // line 50
            yield "                </tbody>

                ";
            // line 53
            yield "                ";
            if (((array_key_exists("free_size", $context) && ((((            // line 54
($context["tbl_storage_engine"] ?? null) == "MYISAM") || (            // line 55
($context["tbl_storage_engine"] ?? null) == "ARIA")) || (            // line 56
($context["tbl_storage_engine"] ?? null) == "MARIA")) || (            // line 57
($context["tbl_storage_engine"] ?? null) == "BDB"))) || ((            // line 58
($context["tbl_storage_engine"] ?? null) == "INNODB") && (($context["innodb_file_per_table"] ?? null) == true)))) {
                // line 59
                yield "                <tfoot>
                    <tr class=\"d-print-none\">
                        <th colspan=\"3\" class=\"center\">
                            <a href=\"";
                // line 62
                yield PhpMyAdmin\Url::getFromRoute("/sql");
                yield "\" data-post=\"";
                yield PhpMyAdmin\Url::getCommon(["db" =>                 // line 63
($context["db"] ?? null), "table" =>                 // line 64
($context["table"] ?? null), "sql_query" => ("OPTIMIZE TABLE " . PhpMyAdmin\Util::backquote(                // line 65
($context["table"] ?? null))), "pos" => 0]);
                // line 67
                yield "\">
                                ";
                // line 68
                yield PhpMyAdmin\Html\Generator::getIcon("b_tbloptimize", _gettext("Optimize table"));
                yield "
                            </a>
                        </th>
                    </tr>
                </tfoot>
                ";
            }
            // line 74
            yield "            </table>
        ";
        }
        // line 76
        yield "
        ";
        // line 77
        $context["avg_size"] = ((array_key_exists("avg_size", $context)) ? (($context["avg_size"] ?? null)) : (null));
        // line 78
        yield "        ";
        $context["avg_unit"] = ((array_key_exists("avg_unit", $context)) ? (($context["avg_unit"] ?? null)) : (null));
        // line 79
        yield "        <table class=\"table table-striped table-hover table-sm w-auto caption-top\">
            <caption>";
yield _gettext("Row statistics");
        // line 80
        yield "</caption>
            <tbody>
                ";
        // line 82
        if (CoreExtension::getAttribute($this->env, $this->source, ($context["showtable"] ?? null), "Row_format", [], "array", true, true, false, 82)) {
            // line 83
            yield "                    <tr>
                    <th class=\"name\">";
yield _gettext("Format");
            // line 84
            yield "</th>
                    ";
            // line 85
            if (((($__internal_compile_2 = ($context["showtable"] ?? null)) && is_array($__internal_compile_2) || $__internal_compile_2 instanceof ArrayAccess ? ($__internal_compile_2["Row_format"] ?? null) : null) == "Fixed")) {
                // line 86
                yield "                        <td class=\"value\">";
yield _gettext("static");
                yield "</td>
                    ";
            } elseif (((($__internal_compile_3 =             // line 87
($context["showtable"] ?? null)) && is_array($__internal_compile_3) || $__internal_compile_3 instanceof ArrayAccess ? ($__internal_compile_3["Row_format"] ?? null) : null) == "Dynamic")) {
                // line 88
                yield "                        <td class=\"value\">";
yield _gettext("dynamic");
                yield "</td>
                    ";
            } else {
                // line 90
                yield "                        <td class=\"value\">";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_4 = ($context["showtable"] ?? null)) && is_array($__internal_compile_4) || $__internal_compile_4 instanceof ArrayAccess ? ($__internal_compile_4["Row_format"] ?? null) : null), "html", null, true);
                yield "</td>
                    ";
            }
            // line 92
            yield "                    </tr>
                ";
        }
        // line 94
        yield "
                ";
        // line 95
        if ( !Twig\Extension\CoreExtension::testEmpty((($__internal_compile_5 = ($context["showtable"] ?? null)) && is_array($__internal_compile_5) || $__internal_compile_5 instanceof ArrayAccess ? ($__internal_compile_5["Create_options"] ?? null) : null))) {
            // line 96
            yield "                    <tr>
                    <th class=\"name\">";
yield _gettext("Options");
            // line 97
            yield "</th>
                    ";
            // line 98
            if (((($__internal_compile_6 = ($context["showtable"] ?? null)) && is_array($__internal_compile_6) || $__internal_compile_6 instanceof ArrayAccess ? ($__internal_compile_6["Create_options"] ?? null) : null) == "partitioned")) {
                // line 99
                yield "                        <td class=\"value\">";
yield _gettext("partitioned");
                yield "</td>
                    ";
            } else {
                // line 101
                yield "                        <td class=\"value\">";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_7 = ($context["showtable"] ?? null)) && is_array($__internal_compile_7) || $__internal_compile_7 instanceof ArrayAccess ? ($__internal_compile_7["Create_options"] ?? null) : null), "html", null, true);
                yield "</td>
                    ";
            }
            // line 103
            yield "                    </tr>
                ";
        }
        // line 105
        yield "
                ";
        // line 106
        if ( !Twig\Extension\CoreExtension::testEmpty(($context["table_collation"] ?? null))) {
            // line 107
            yield "                    <tr>
                    <th class=\"name\">";
yield _gettext("Collation");
            // line 108
            yield "</th>
                    <td class=\"value\">
                        <dfn title=\"";
            // line 110
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["table_collation"] ?? null), "description", [], "any", false, false, false, 110), "html", null, true);
            yield "\">
                            ";
            // line 111
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["table_collation"] ?? null), "name", [], "any", false, false, false, 111), "html", null, true);
            yield "
                        </dfn>
                    </td>
                    </tr>
                ";
        }
        // line 116
        yield "
                ";
        // line 117
        if (( !($context["is_innodb"] ?? null) && CoreExtension::getAttribute($this->env, $this->source, ($context["showtable"] ?? null), "Rows", [], "array", true, true, false, 117))) {
            // line 118
            yield "                    <tr>
                    <th class=\"name\">";
yield _gettext("Rows");
            // line 119
            yield "</th>
                    <td class=\"value\">";
            // line 120
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(PhpMyAdmin\Util::formatNumber((($__internal_compile_8 = ($context["showtable"] ?? null)) && is_array($__internal_compile_8) || $__internal_compile_8 instanceof ArrayAccess ? ($__internal_compile_8["Rows"] ?? null) : null), 0), "html", null, true);
            yield "</td>
                    </tr>
                ";
        }
        // line 123
        yield "
                ";
        // line 124
        if ((( !($context["is_innodb"] ?? null) && CoreExtension::getAttribute($this->env, $this->source,         // line 125
($context["showtable"] ?? null), "Avg_row_length", [], "array", true, true, false, 125)) && ((($__internal_compile_9 =         // line 126
($context["showtable"] ?? null)) && is_array($__internal_compile_9) || $__internal_compile_9 instanceof ArrayAccess ? ($__internal_compile_9["Avg_row_length"] ?? null) : null) > 0))) {
            // line 127
            yield "                    <tr>
                    <th class=\"name\">";
yield _gettext("Row length");
            // line 128
            yield "</th>
                    ";
            // line 129
            $context["avg_row_length"] = PhpMyAdmin\Util::formatByteDown((($__internal_compile_10 = ($context["showtable"] ?? null)) && is_array($__internal_compile_10) || $__internal_compile_10 instanceof ArrayAccess ? ($__internal_compile_10["Avg_row_length"] ?? null) : null), 6, 1);
            // line 130
            yield "                    <td class=\"value\">";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_11 = ($context["avg_row_length"] ?? null)) && is_array($__internal_compile_11) || $__internal_compile_11 instanceof ArrayAccess ? ($__internal_compile_11[0] ?? null) : null), "html", null, true);
            yield " ";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_12 = ($context["avg_row_length"] ?? null)) && is_array($__internal_compile_12) || $__internal_compile_12 instanceof ArrayAccess ? ($__internal_compile_12[1] ?? null) : null), "html", null, true);
            yield "</td>
                    </tr>
                ";
        }
        // line 133
        yield "
                ";
        // line 134
        if ((((( !($context["is_innodb"] ?? null) && CoreExtension::getAttribute($this->env, $this->source,         // line 135
($context["showtable"] ?? null), "Data_length", [], "array", true, true, false, 135)) && CoreExtension::getAttribute($this->env, $this->source,         // line 136
($context["showtable"] ?? null), "Rows", [], "array", true, true, false, 136)) && ((($__internal_compile_13 =         // line 137
($context["showtable"] ?? null)) && is_array($__internal_compile_13) || $__internal_compile_13 instanceof ArrayAccess ? ($__internal_compile_13["Rows"] ?? null) : null) > 0)) && (        // line 138
($context["mergetable"] ?? null) == false))) {
            // line 139
            yield "                    <tr>
                    <th class=\"name\">";
yield _gettext("Row size");
            // line 140
            yield "</th>
                    <td class=\"value\">";
            // line 141
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["avg_size"] ?? null), "html", null, true);
            yield " ";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["avg_unit"] ?? null), "html", null, true);
            yield "</td>
                    </tr>
                ";
        }
        // line 144
        yield "
                ";
        // line 145
        if (CoreExtension::getAttribute($this->env, $this->source, ($context["showtable"] ?? null), "Auto_increment", [], "array", true, true, false, 145)) {
            // line 146
            yield "                    <tr>
                    <th class=\"name\">";
yield _gettext("Next autoindex");
            // line 147
            yield "</th>
                    <td class=\"value\">";
            // line 148
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(PhpMyAdmin\Util::formatNumber((($__internal_compile_14 = ($context["showtable"] ?? null)) && is_array($__internal_compile_14) || $__internal_compile_14 instanceof ArrayAccess ? ($__internal_compile_14["Auto_increment"] ?? null) : null), 0), "html", null, true);
            yield "</td>
                    </tr>
                ";
        }
        // line 151
        yield "
                ";
        // line 152
        if (CoreExtension::getAttribute($this->env, $this->source, ($context["showtable"] ?? null), "Create_time", [], "array", true, true, false, 152)) {
            // line 153
            yield "                    <tr>
                    <th class=\"name\">";
yield _gettext("Creation");
            // line 154
            yield "</th>
                    <td class=\"value\">";
            // line 155
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(PhpMyAdmin\Util::localisedDate($this->extensions['Twig\Extension\CoreExtension']->formatDate((($__internal_compile_15 = ($context["showtable"] ?? null)) && is_array($__internal_compile_15) || $__internal_compile_15 instanceof ArrayAccess ? ($__internal_compile_15["Create_time"] ?? null) : null), "U")), "html", null, true);
            yield "</td>
                    </tr>
                ";
        }
        // line 158
        yield "
                ";
        // line 159
        if (CoreExtension::getAttribute($this->env, $this->source, ($context["showtable"] ?? null), "Update_time", [], "array", true, true, false, 159)) {
            // line 160
            yield "                    <tr>
                    <th class=\"name\">";
yield _gettext("Last update");
            // line 161
            yield "</th>
                    <td class=\"value\">";
            // line 162
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(PhpMyAdmin\Util::localisedDate($this->extensions['Twig\Extension\CoreExtension']->formatDate((($__internal_compile_16 = ($context["showtable"] ?? null)) && is_array($__internal_compile_16) || $__internal_compile_16 instanceof ArrayAccess ? ($__internal_compile_16["Update_time"] ?? null) : null), "U")), "html", null, true);
            yield "</td>
                    </tr>
                ";
        }
        // line 165
        yield "
                ";
        // line 166
        if (CoreExtension::getAttribute($this->env, $this->source, ($context["showtable"] ?? null), "Check_time", [], "array", true, true, false, 166)) {
            // line 167
            yield "                    <tr>
                    <th class=\"name\">";
yield _gettext("Last check");
            // line 168
            yield "</th>
                    <td class=\"value\">";
            // line 169
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(PhpMyAdmin\Util::localisedDate($this->extensions['Twig\Extension\CoreExtension']->formatDate((($__internal_compile_17 = ($context["showtable"] ?? null)) && is_array($__internal_compile_17) || $__internal_compile_17 instanceof ArrayAccess ? ($__internal_compile_17["Check_time"] ?? null) : null), "U")), "html", null, true);
            yield "</td>
                    </tr>
                ";
        }
        // line 172
        yield "            </tbody>
        </table>
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
        return "table/structure/display_table_stats.twig";
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
        return array (  451 => 172,  445 => 169,  442 => 168,  438 => 167,  436 => 166,  433 => 165,  427 => 162,  424 => 161,  420 => 160,  418 => 159,  415 => 158,  409 => 155,  406 => 154,  402 => 153,  400 => 152,  397 => 151,  391 => 148,  388 => 147,  384 => 146,  382 => 145,  379 => 144,  371 => 141,  368 => 140,  364 => 139,  362 => 138,  361 => 137,  360 => 136,  359 => 135,  358 => 134,  355 => 133,  346 => 130,  344 => 129,  341 => 128,  337 => 127,  335 => 126,  334 => 125,  333 => 124,  330 => 123,  324 => 120,  321 => 119,  317 => 118,  315 => 117,  312 => 116,  304 => 111,  300 => 110,  296 => 108,  292 => 107,  290 => 106,  287 => 105,  283 => 103,  277 => 101,  271 => 99,  269 => 98,  266 => 97,  262 => 96,  260 => 95,  257 => 94,  253 => 92,  247 => 90,  241 => 88,  239 => 87,  234 => 86,  232 => 85,  229 => 84,  225 => 83,  223 => 82,  219 => 80,  215 => 79,  212 => 78,  210 => 77,  207 => 76,  203 => 74,  194 => 68,  191 => 67,  189 => 65,  188 => 64,  187 => 63,  184 => 62,  179 => 59,  177 => 58,  176 => 57,  175 => 56,  174 => 55,  173 => 54,  171 => 53,  167 => 50,  161 => 47,  157 => 46,  154 => 45,  150 => 44,  148 => 43,  145 => 42,  139 => 39,  135 => 38,  132 => 37,  125 => 34,  121 => 33,  118 => 32,  114 => 31,  112 => 30,  109 => 29,  103 => 26,  99 => 25,  96 => 24,  92 => 23,  90 => 22,  84 => 19,  80 => 18,  77 => 17,  71 => 14,  67 => 13,  65 => 12,  61 => 10,  55 => 7,  52 => 6,  48 => 5,  46 => 4,  43 => 3,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "table/structure/display_table_stats.twig", "/home/oliverbob/ginto/vendor/phpmyadmin/phpmyadmin/templates/table/structure/display_table_stats.twig");
    }
}
