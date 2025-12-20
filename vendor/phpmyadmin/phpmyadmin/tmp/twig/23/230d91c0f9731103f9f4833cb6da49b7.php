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

/* table/structure/display_structure.twig */
class __TwigTemplate_fc523dafb3a646914a725f5bf31d4a66 extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'content' => [$this, 'block_content'],
        ];
    }

    protected function doGetParent(array $context)
    {
        // line 1
        return "table/page_with_secondary_tabs.twig";
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        $this->parent = $this->loadTemplate("table/page_with_secondary_tabs.twig", "table/structure/display_structure.twig", 1);
        yield from $this->parent->unwrap()->yield($context, array_merge($this->blocks, $blocks));
    }

    // line 2
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 3
        yield "<h1 class=\"d-none d-print-block\">";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["table"] ?? null), "html", null, true);
        yield "</h1>
<form method=\"post\" action=\"";
        // line 4
        yield PhpMyAdmin\Url::getFromRoute("/table/structure");
        yield "\" name=\"fieldsForm\" id=\"fieldsForm\">
    ";
        // line 5
        yield PhpMyAdmin\Url::getHiddenInputs(($context["db"] ?? null), ($context["table"] ?? null));
        yield "
    <input type=\"hidden\" name=\"table_type\" value=";
        // line 7
        if (($context["db_is_system_schema"] ?? null)) {
            // line 8
            yield "\"information_schema\"";
        } elseif (        // line 9
($context["tbl_is_view"] ?? null)) {
            // line 10
            yield "\"view\"";
        } else {
            // line 12
            yield "\"table\"";
        }
        // line 13
        yield ">
    <div class=\"table-responsive-md\">
    <table id=\"tablestructure\" class=\"table table-striped table-hover w-auto align-middle\">
        ";
        // line 17
        yield "        <thead>
            <tr>
                <th class=\"d-print-none\"></th>
                <th>#</th>
                <th>";
yield _gettext("Name");
        // line 21
        yield "</th>
                <th>";
yield _gettext("Type");
        // line 22
        yield "</th>
                <th>";
yield _gettext("Collation");
        // line 23
        yield "</th>
                <th>";
yield _gettext("Attributes");
        // line 24
        yield "</th>
                <th>";
yield _gettext("Null");
        // line 25
        yield "</th>
                <th>";
yield _gettext("Default");
        // line 26
        yield "</th>
                ";
        // line 27
        if (($context["show_column_comments"] ?? null)) {
            // line 28
            yield "<th>";
yield _gettext("Comments");
            yield "</th>";
        }
        // line 30
        yield "                <th>";
yield _gettext("Extra");
        yield "</th>
                ";
        // line 32
        yield "                ";
        if (( !($context["db_is_system_schema"] ?? null) &&  !($context["tbl_is_view"] ?? null))) {
            // line 33
            yield "                    <th colspan=\"";
            yield ((PhpMyAdmin\Util::showIcons("ActionLinksMode")) ? ("8") : ("9"));
            // line 34
            yield "\" class=\"action d-print-none\">";
yield _gettext("Action");
            yield "</th>
                ";
        }
        // line 36
        yield "            </tr>
        </thead>
        <tbody>
        ";
        // line 40
        yield "        ";
        $context["rownum"] = 0;
        // line 41
        yield "        ";
        $context['_parent'] = $context;
        $context['_seq'] = CoreExtension::ensureTraversable(($context["fields"] ?? null));
        foreach ($context['_seq'] as $context["_key"] => $context["row"]) {
            // line 42
            yield "            ";
            $context["rownum"] = (($context["rownum"] ?? null) + 1);
            // line 43
            yield "
            ";
            // line 44
            $context["extracted_columnspec"] = (($__internal_compile_0 = ($context["extracted_columnspecs"] ?? null)) && is_array($__internal_compile_0) || $__internal_compile_0 instanceof ArrayAccess ? ($__internal_compile_0[($context["rownum"] ?? null)] ?? null) : null);
            // line 45
            yield "            ";
            $context["field_name"] = $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_1 = $context["row"]) && is_array($__internal_compile_1) || $__internal_compile_1 instanceof ArrayAccess ? ($__internal_compile_1["Field"] ?? null) : null));
            // line 46
            yield "            ";
            // line 47
            yield "            ";
            $context["comments"] = (($__internal_compile_2 = ($context["row_comments"] ?? null)) && is_array($__internal_compile_2) || $__internal_compile_2 instanceof ArrayAccess ? ($__internal_compile_2[($context["rownum"] ?? null)] ?? null) : null);
            // line 48
            yield "            ";
            // line 49
            yield "
        <tr>
            <td class=\"text-center d-print-none\">
                <input type=\"checkbox\" class=\"checkall\" name=\"selected_fld[]\" value=\"";
            // line 52
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_3 = $context["row"]) && is_array($__internal_compile_3) || $__internal_compile_3 instanceof ArrayAccess ? ($__internal_compile_3["Field"] ?? null) : null), "html", null, true);
            yield "\" id=\"checkbox_row_";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["rownum"] ?? null), "html", null, true);
            yield "\">
            </td>
            <td class=\"text-end\">";
            // line 54
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["rownum"] ?? null), "html", null, true);
            yield "</td>
            <th class=\"text-nowrap\">
                <label for=\"checkbox_row_";
            // line 56
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["rownum"] ?? null), "html", null, true);
            yield "\">
                    ";
            // line 57
            if (CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["displayed_fields"] ?? null), ($context["rownum"] ?? null), [], "array", false, true, false, 57), "comment", [], "any", true, true, false, 57)) {
                // line 58
                yield "                        <span class=\"commented_column\" title=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, (($__internal_compile_4 = ($context["displayed_fields"] ?? null)) && is_array($__internal_compile_4) || $__internal_compile_4 instanceof ArrayAccess ? ($__internal_compile_4[($context["rownum"] ?? null)] ?? null) : null), "comment", [], "any", false, false, false, 58), "html", null, true);
                yield "\">";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, (($__internal_compile_5 = ($context["displayed_fields"] ?? null)) && is_array($__internal_compile_5) || $__internal_compile_5 instanceof ArrayAccess ? ($__internal_compile_5[($context["rownum"] ?? null)] ?? null) : null), "text", [], "any", false, false, false, 58), "html", null, true);
                yield "</span>
                    ";
            } else {
                // line 60
                yield "                        ";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, (($__internal_compile_6 = ($context["displayed_fields"] ?? null)) && is_array($__internal_compile_6) || $__internal_compile_6 instanceof ArrayAccess ? ($__internal_compile_6[($context["rownum"] ?? null)] ?? null) : null), "text", [], "any", false, false, false, 60), "html", null, true);
                yield "
                    ";
            }
            // line 62
            yield "                    ";
            yield CoreExtension::getAttribute($this->env, $this->source, (($__internal_compile_7 = ($context["displayed_fields"] ?? null)) && is_array($__internal_compile_7) || $__internal_compile_7 instanceof ArrayAccess ? ($__internal_compile_7[($context["rownum"] ?? null)] ?? null) : null), "icon", [], "any", false, false, false, 62);
            yield "
                </label>
            </th>
            <td";
            // line 65
            yield (((("set" != (($__internal_compile_8 = ($context["extracted_columnspec"] ?? null)) && is_array($__internal_compile_8) || $__internal_compile_8 instanceof ArrayAccess ? ($__internal_compile_8["type"] ?? null) : null)) && ("enum" != (($__internal_compile_9 = ($context["extracted_columnspec"] ?? null)) && is_array($__internal_compile_9) || $__internal_compile_9 instanceof ArrayAccess ? ($__internal_compile_9["type"] ?? null) : null)))) ? (" class=\"text-nowrap\"") : (""));
            yield ">
                <bdo dir=\"ltr\" lang=\"en\">
                    ";
            // line 67
            yield (($__internal_compile_10 = ($context["extracted_columnspec"] ?? null)) && is_array($__internal_compile_10) || $__internal_compile_10 instanceof ArrayAccess ? ($__internal_compile_10["displayed_type"] ?? null) : null);
            yield "
                    ";
            // line 68
            if (((( !(null === CoreExtension::getAttribute($this->env, $this->source, ($context["relation_parameters"] ?? null), "columnCommentsFeature", [], "any", false, false, false, 68)) &&  !(null === CoreExtension::getAttribute($this->env, $this->source, ($context["relation_parameters"] ?? null), "browserTransformationFeature", [], "any", false, false, false, 68))) && ($context["browse_mime"] ?? null)) && CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source,             // line 69
($context["mime_map"] ?? null), (($__internal_compile_11 = $context["row"]) && is_array($__internal_compile_11) || $__internal_compile_11 instanceof ArrayAccess ? ($__internal_compile_11["Field"] ?? null) : null), [], "array", false, true, false, 69), "mimetype", [], "array", true, true, false, 69))) {
                // line 70
                yield "                        <br>";
yield _gettext("Media type:");
                yield " ";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(Twig\Extension\CoreExtension::lower($this->env->getCharset(), Twig\Extension\CoreExtension::replace((($__internal_compile_12 = (($__internal_compile_13 = ($context["mime_map"] ?? null)) && is_array($__internal_compile_13) || $__internal_compile_13 instanceof ArrayAccess ? ($__internal_compile_13[(($__internal_compile_14 = $context["row"]) && is_array($__internal_compile_14) || $__internal_compile_14 instanceof ArrayAccess ? ($__internal_compile_14["Field"] ?? null) : null)] ?? null) : null)) && is_array($__internal_compile_12) || $__internal_compile_12 instanceof ArrayAccess ? ($__internal_compile_12["mimetype"] ?? null) : null), ["_" => "/"])), "html", null, true);
                yield "
                    ";
            }
            // line 72
            yield "                </bdo>
            </td>
            <td>
            ";
            // line 75
            if ( !Twig\Extension\CoreExtension::testEmpty((($__internal_compile_15 = $context["row"]) && is_array($__internal_compile_15) || $__internal_compile_15 instanceof ArrayAccess ? ($__internal_compile_15["Collation"] ?? null) : null))) {
                // line 76
                yield "                <dfn title=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, (($__internal_compile_16 = ($context["collations"] ?? null)) && is_array($__internal_compile_16) || $__internal_compile_16 instanceof ArrayAccess ? ($__internal_compile_16[(($__internal_compile_17 = $context["row"]) && is_array($__internal_compile_17) || $__internal_compile_17 instanceof ArrayAccess ? ($__internal_compile_17["Collation"] ?? null) : null)] ?? null) : null), "description", [], "any", false, false, false, 76), "html", null, true);
                yield "\">";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, (($__internal_compile_18 = ($context["collations"] ?? null)) && is_array($__internal_compile_18) || $__internal_compile_18 instanceof ArrayAccess ? ($__internal_compile_18[(($__internal_compile_19 = $context["row"]) && is_array($__internal_compile_19) || $__internal_compile_19 instanceof ArrayAccess ? ($__internal_compile_19["Collation"] ?? null) : null)] ?? null) : null), "name", [], "any", false, false, false, 76), "html", null, true);
                yield "</dfn>
            ";
            }
            // line 78
            yield "            </td>
            <td class=\"column_attribute text-nowrap\">";
            // line 79
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_20 = ($context["attributes"] ?? null)) && is_array($__internal_compile_20) || $__internal_compile_20 instanceof ArrayAccess ? ($__internal_compile_20[($context["rownum"] ?? null)] ?? null) : null), "html", null, true);
            yield "</td>
            <td>";
            // line 80
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(((((($__internal_compile_21 = $context["row"]) && is_array($__internal_compile_21) || $__internal_compile_21 instanceof ArrayAccess ? ($__internal_compile_21["Null"] ?? null) : null) == "YES")) ? (_gettext("Yes")) : (_gettext("No"))), "html", null, true);
            yield "</td>
            <td class=\"text-nowrap\">";
            // line 82
            if ( !(null === (($__internal_compile_22 = $context["row"]) && is_array($__internal_compile_22) || $__internal_compile_22 instanceof ArrayAccess ? ($__internal_compile_22["Default"] ?? null) : null))) {
                // line 83
                if (((($__internal_compile_23 = ($context["extracted_columnspec"] ?? null)) && is_array($__internal_compile_23) || $__internal_compile_23 instanceof ArrayAccess ? ($__internal_compile_23["type"] ?? null) : null) == "bit")) {
                    // line 84
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(PhpMyAdmin\Util::convertBitDefaultValue((($__internal_compile_24 = $context["row"]) && is_array($__internal_compile_24) || $__internal_compile_24 instanceof ArrayAccess ? ($__internal_compile_24["Default"] ?? null) : null)), "html", null, true);
                } else {
                    // line 86
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_25 = $context["row"]) && is_array($__internal_compile_25) || $__internal_compile_25 instanceof ArrayAccess ? ($__internal_compile_25["Default"] ?? null) : null), "html", null, true);
                }
            } elseif (((($__internal_compile_26 =             // line 88
$context["row"]) && is_array($__internal_compile_26) || $__internal_compile_26 instanceof ArrayAccess ? ($__internal_compile_26["Null"] ?? null) : null) == "YES")) {
                // line 89
                yield "<em>NULL</em>";
            } else {
                // line 91
                yield "<em>";
yield _pgettext("None for default", "None");
                yield "</em>";
            }
            // line 93
            yield "</td>
            ";
            // line 94
            if (($context["show_column_comments"] ?? null)) {
                // line 95
                yield "                <td>
                    ";
                // line 96
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["comments"] ?? null), "html", null, true);
                yield "
                </td>
            ";
            }
            // line 99
            yield "            <td class=\"text-nowrap\">";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(Twig\Extension\CoreExtension::upper($this->env->getCharset(), (($__internal_compile_27 = $context["row"]) && is_array($__internal_compile_27) || $__internal_compile_27 instanceof ArrayAccess ? ($__internal_compile_27["Extra"] ?? null) : null)), "html", null, true);
            yield "</td>
            ";
            // line 100
            if (( !($context["tbl_is_view"] ?? null) &&  !($context["db_is_system_schema"] ?? null))) {
                // line 101
                yield "                <td class=\"edit text-center d-print-none\">
                    <a class=\"change_column_anchor ajax\" href=\"";
                // line 102
                yield PhpMyAdmin\Url::getFromRoute("/table/structure/change", ["db" =>                 // line 103
($context["db"] ?? null), "table" =>                 // line 104
($context["table"] ?? null), "field" => (($__internal_compile_28 =                 // line 105
$context["row"]) && is_array($__internal_compile_28) || $__internal_compile_28 instanceof ArrayAccess ? ($__internal_compile_28["Field"] ?? null) : null), "change_column" => 1]);
                // line 107
                yield "\">
                      ";
                // line 108
                yield PhpMyAdmin\Html\Generator::getIcon("b_edit", _gettext("Change"));
                yield "
                    </a>
                </td>
                <td class=\"drop text-center d-print-none\">
                    <a class=\"drop_column_anchor ajax\" href=\"";
                // line 112
                yield PhpMyAdmin\Url::getFromRoute("/sql");
                yield "\" data-post=\"";
                yield PhpMyAdmin\Url::getCommon(["db" =>                 // line 113
($context["db"] ?? null), "table" =>                 // line 114
($context["table"] ?? null), "sql_query" => (((("ALTER TABLE " . PhpMyAdmin\Util::backquote(                // line 115
($context["table"] ?? null))) . " DROP ") . PhpMyAdmin\Util::backquote((($__internal_compile_29 = $context["row"]) && is_array($__internal_compile_29) || $__internal_compile_29 instanceof ArrayAccess ? ($__internal_compile_29["Field"] ?? null) : null))) . ";"), "dropped_column" => (($__internal_compile_30 =                 // line 116
$context["row"]) && is_array($__internal_compile_30) || $__internal_compile_30 instanceof ArrayAccess ? ($__internal_compile_30["Field"] ?? null) : null), "purge" => true, "message_to_show" => Twig\Extension\CoreExtension::sprintf(_gettext("Column %s has been dropped."), $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_31 =                 // line 118
$context["row"]) && is_array($__internal_compile_31) || $__internal_compile_31 instanceof ArrayAccess ? ($__internal_compile_31["Field"] ?? null) : null)))], "", false);
                // line 119
                yield "\">
                      ";
                // line 120
                yield PhpMyAdmin\Html\Generator::getIcon("b_drop", _gettext("Drop"));
                yield "
                    </a>
                </td>
            ";
            }
            // line 124
            yield "
            ";
            // line 125
            if (( !($context["tbl_is_view"] ?? null) &&  !($context["db_is_system_schema"] ?? null))) {
                // line 126
                yield "                ";
                $context["type"] = (( !Twig\Extension\CoreExtension::testEmpty((($__internal_compile_32 = ($context["extracted_columnspec"] ?? null)) && is_array($__internal_compile_32) || $__internal_compile_32 instanceof ArrayAccess ? ($__internal_compile_32["print_type"] ?? null) : null))) ? ((($__internal_compile_33 = ($context["extracted_columnspec"] ?? null)) && is_array($__internal_compile_33) || $__internal_compile_33 instanceof ArrayAccess ? ($__internal_compile_33["print_type"] ?? null) : null)) : (""));
                // line 127
                yield "                <td class=\"d-print-none\">
                  ";
                // line 128
                if (($context["hide_structure_actions"] ?? null)) {
                    // line 129
                    yield "                  <div class=\"dropdown\">
                    <button class=\"btn btn-link p-0 dropdown-toggle\" type=\"button\" id=\"moreActionsButton\" data-bs-toggle=\"dropdown\" aria-expanded=\"false\">";
yield _gettext("More");
                    // line 130
                    yield "</button>
                    <ul class=\"dropdown-menu dropdown-menu-end\" aria-labelledby=\"moreActionsButton\">
                  ";
                } else {
                    // line 133
                    yield "                    <ul class=\"nav\">
                  ";
                }
                // line 135
                yield "                        <li class=\"";
                yield (( !($context["hide_structure_actions"] ?? null)) ? ("nav-item ") : (""));
                yield "primary text-nowrap\">
                          ";
                // line 136
                if (((((($context["type"] ?? null) == "text") || (($context["type"] ?? null) == "blob")) || (($context["tbl_storage_engine"] ?? null) == "ARCHIVE")) || (($context["primary"] ?? null) && CoreExtension::getAttribute($this->env, $this->source, ($context["primary"] ?? null), "hasColumn", [($context["field_name"] ?? null)], "method", false, false, false, 136)))) {
                    // line 137
                    yield "                            <span class=\"";
                    yield ((($context["hide_structure_actions"] ?? null)) ? ("dropdown-item-text") : ("nav-link px-1"));
                    yield " disabled\">";
                    yield PhpMyAdmin\Html\Generator::getIcon("bd_primary", _gettext("Primary"));
                    yield "</span>
                          ";
                } else {
                    // line 139
                    yield "                            <a rel=\"samepage\" class=\"";
                    yield ((($context["hide_structure_actions"] ?? null)) ? ("dropdown-item") : ("nav-link px-1"));
                    yield " ajax add_key d-print-none add_primary_key_anchor\" href=\"";
                    yield PhpMyAdmin\Url::getFromRoute("/table/structure/add-key");
                    yield "\" data-post=\"";
                    yield PhpMyAdmin\Url::getCommon(["db" =>                     // line 140
($context["db"] ?? null), "table" =>                     // line 141
($context["table"] ?? null), "sql_query" => ((((("ALTER TABLE " . PhpMyAdmin\Util::backquote(                    // line 142
($context["table"] ?? null))) . ((($context["primary"] ?? null)) ? (" DROP PRIMARY KEY,") : (""))) . " ADD PRIMARY KEY(") . PhpMyAdmin\Util::backquote((($__internal_compile_34 = $context["row"]) && is_array($__internal_compile_34) || $__internal_compile_34 instanceof ArrayAccess ? ($__internal_compile_34["Field"] ?? null) : null))) . ");"), "message_to_show" => Twig\Extension\CoreExtension::sprintf(_gettext("A primary key has been added on %s."), $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_35 =                     // line 143
$context["row"]) && is_array($__internal_compile_35) || $__internal_compile_35 instanceof ArrayAccess ? ($__internal_compile_35["Field"] ?? null) : null)))], "", false);
                    // line 144
                    yield "\">
                              ";
                    // line 145
                    yield PhpMyAdmin\Html\Generator::getIcon("b_primary", _gettext("Primary"));
                    yield "
                            </a>
                          ";
                }
                // line 148
                yield "                        </li>

                        <li class=\"";
                // line 150
                yield (( !($context["hide_structure_actions"] ?? null)) ? ("nav-item ") : (""));
                yield "add_unique unique text-nowrap\">
                          ";
                // line 151
                if ((((($context["type"] ?? null) == "text") || (($context["type"] ?? null) == "blob")) || (($context["tbl_storage_engine"] ?? null) == "ARCHIVE"))) {
                    // line 152
                    yield "                            <span class=\"";
                    yield ((($context["hide_structure_actions"] ?? null)) ? ("dropdown-item-text") : ("nav-link px-1"));
                    yield " disabled\">";
                    yield PhpMyAdmin\Html\Generator::getIcon("bd_unique", _gettext("Unique"));
                    yield "</span>
                          ";
                } else {
                    // line 154
                    yield "                            <a rel=\"samepage\" class=\"";
                    yield ((($context["hide_structure_actions"] ?? null)) ? ("dropdown-item") : ("nav-link px-1"));
                    yield " ajax add_key d-print-none add_unique_anchor\" href=\"";
                    yield PhpMyAdmin\Url::getFromRoute("/table/structure/add-key");
                    yield "\" data-post=\"";
                    yield PhpMyAdmin\Url::getCommon(["db" =>                     // line 155
($context["db"] ?? null), "table" =>                     // line 156
($context["table"] ?? null), "sql_query" => (((("ALTER TABLE " . PhpMyAdmin\Util::backquote(                    // line 157
($context["table"] ?? null))) . " ADD UNIQUE(") . PhpMyAdmin\Util::backquote((($__internal_compile_36 = $context["row"]) && is_array($__internal_compile_36) || $__internal_compile_36 instanceof ArrayAccess ? ($__internal_compile_36["Field"] ?? null) : null))) . ");"), "message_to_show" => Twig\Extension\CoreExtension::sprintf(_gettext("An index has been added on %s."), $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_37 =                     // line 158
$context["row"]) && is_array($__internal_compile_37) || $__internal_compile_37 instanceof ArrayAccess ? ($__internal_compile_37["Field"] ?? null) : null)))], "", false);
                    // line 159
                    yield "\">
                              ";
                    // line 160
                    yield PhpMyAdmin\Html\Generator::getIcon("b_unique", _gettext("Unique"));
                    yield "
                            </a>
                          ";
                }
                // line 163
                yield "                        </li>

                        <li class=\"";
                // line 165
                yield (( !($context["hide_structure_actions"] ?? null)) ? ("nav-item ") : (""));
                yield "add_index text-nowrap\">
                          ";
                // line 166
                if ((((($context["type"] ?? null) == "text") || (($context["type"] ?? null) == "blob")) || (($context["tbl_storage_engine"] ?? null) == "ARCHIVE"))) {
                    // line 167
                    yield "                            <span class=\"";
                    yield ((($context["hide_structure_actions"] ?? null)) ? ("dropdown-item-text") : ("nav-link px-1"));
                    yield " disabled\">";
                    yield PhpMyAdmin\Html\Generator::getIcon("bd_index", _gettext("Index"));
                    yield "</span>
                          ";
                } else {
                    // line 169
                    yield "                            <a rel=\"samepage\" class=\"";
                    yield ((($context["hide_structure_actions"] ?? null)) ? ("dropdown-item") : ("nav-link px-1"));
                    yield " ajax add_key d-print-none add_index_anchor\" href=\"";
                    yield PhpMyAdmin\Url::getFromRoute("/table/structure/add-key");
                    yield "\" data-post=\"";
                    yield PhpMyAdmin\Url::getCommon(["db" =>                     // line 170
($context["db"] ?? null), "table" =>                     // line 171
($context["table"] ?? null), "sql_query" => (((("ALTER TABLE " . PhpMyAdmin\Util::backquote(                    // line 172
($context["table"] ?? null))) . " ADD INDEX(") . PhpMyAdmin\Util::backquote((($__internal_compile_38 = $context["row"]) && is_array($__internal_compile_38) || $__internal_compile_38 instanceof ArrayAccess ? ($__internal_compile_38["Field"] ?? null) : null))) . ");"), "message_to_show" => Twig\Extension\CoreExtension::sprintf(_gettext("An index has been added on %s."), $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_39 =                     // line 173
$context["row"]) && is_array($__internal_compile_39) || $__internal_compile_39 instanceof ArrayAccess ? ($__internal_compile_39["Field"] ?? null) : null)))], "", false);
                    // line 174
                    yield "\">
                              ";
                    // line 175
                    yield PhpMyAdmin\Html\Generator::getIcon("b_index", _gettext("Index"));
                    yield "
                            </a>
                          ";
                }
                // line 178
                yield "                        </li>

                        ";
                // line 180
                $context["spatial_types"] = ["geometry", "point", "linestring", "polygon", "multipoint", "multilinestring", "multipolygon", "geomtrycollection"];
                // line 190
                yield "                        <li class=\"";
                yield (( !($context["hide_structure_actions"] ?? null)) ? ("nav-item ") : (""));
                yield "spatial text-nowrap\">
                          ";
                // line 191
                if (((((($context["type"] ?? null) == "text") || (($context["type"] ?? null) == "blob")) || (($context["tbl_storage_engine"] ?? null) == "ARCHIVE")) || (!CoreExtension::inFilter(($context["type"] ?? null), ($context["spatial_types"] ?? null)) && ((($context["tbl_storage_engine"] ?? null) == "MYISAM") || (($context["mysql_int_version"] ?? null) >= 50705))))) {
                    // line 192
                    yield "                            <span class=\"";
                    yield ((($context["hide_structure_actions"] ?? null)) ? ("dropdown-item-text") : ("nav-link px-1"));
                    yield " disabled\">";
                    yield PhpMyAdmin\Html\Generator::getIcon("bd_spatial", _gettext("Spatial"));
                    yield "</span>
                          ";
                } else {
                    // line 194
                    yield "                            <a rel=\"samepage\" class=\"";
                    yield ((($context["hide_structure_actions"] ?? null)) ? ("dropdown-item") : ("nav-link px-1"));
                    yield " ajax add_key d-print-none add_spatial_anchor\" href=\"";
                    yield PhpMyAdmin\Url::getFromRoute("/table/structure/add-key");
                    yield "\" data-post=\"";
                    yield PhpMyAdmin\Url::getCommon(["db" =>                     // line 195
($context["db"] ?? null), "table" =>                     // line 196
($context["table"] ?? null), "sql_query" => (((("ALTER TABLE " . PhpMyAdmin\Util::backquote(                    // line 197
($context["table"] ?? null))) . " ADD SPATIAL(") . PhpMyAdmin\Util::backquote((($__internal_compile_40 = $context["row"]) && is_array($__internal_compile_40) || $__internal_compile_40 instanceof ArrayAccess ? ($__internal_compile_40["Field"] ?? null) : null))) . ");"), "message_to_show" => Twig\Extension\CoreExtension::sprintf(_gettext("An index has been added on %s."), $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_41 =                     // line 198
$context["row"]) && is_array($__internal_compile_41) || $__internal_compile_41 instanceof ArrayAccess ? ($__internal_compile_41["Field"] ?? null) : null)))], "", false);
                    // line 199
                    yield "\">
                              ";
                    // line 200
                    yield PhpMyAdmin\Html\Generator::getIcon("b_spatial", _gettext("Spatial"));
                    yield "
                            </a>
                          ";
                }
                // line 203
                yield "                        </li>

                        ";
                // line 206
                yield "                        <li class=\"";
                yield (( !($context["hide_structure_actions"] ?? null)) ? ("nav-item ") : (""));
                yield "fulltext text-nowrap\">
                        ";
                // line 207
                if ((( !Twig\Extension\CoreExtension::testEmpty(($context["tbl_storage_engine"] ?? null)) && ((((                // line 208
($context["tbl_storage_engine"] ?? null) == "MYISAM") || (                // line 209
($context["tbl_storage_engine"] ?? null) == "ARIA")) || (                // line 210
($context["tbl_storage_engine"] ?? null) == "MARIA")) || ((                // line 211
($context["tbl_storage_engine"] ?? null) == "INNODB") && (($context["mysql_int_version"] ?? null) >= 50604)))) && (CoreExtension::inFilter("text",                 // line 212
($context["type"] ?? null)) || CoreExtension::inFilter("char", ($context["type"] ?? null))))) {
                    // line 213
                    yield "                            <a rel=\"samepage\" class=\"";
                    yield ((($context["hide_structure_actions"] ?? null)) ? ("dropdown-item") : ("nav-link px-1"));
                    yield " ajax add_key add_fulltext_anchor\" href=\"";
                    yield PhpMyAdmin\Url::getFromRoute("/table/structure/add-key");
                    yield "\" data-post=\"";
                    yield PhpMyAdmin\Url::getCommon(["db" =>                     // line 214
($context["db"] ?? null), "table" =>                     // line 215
($context["table"] ?? null), "sql_query" => (((("ALTER TABLE " . PhpMyAdmin\Util::backquote(                    // line 216
($context["table"] ?? null))) . " ADD FULLTEXT(") . PhpMyAdmin\Util::backquote((($__internal_compile_42 = $context["row"]) && is_array($__internal_compile_42) || $__internal_compile_42 instanceof ArrayAccess ? ($__internal_compile_42["Field"] ?? null) : null))) . ");"), "message_to_show" => Twig\Extension\CoreExtension::sprintf(_gettext("An index has been added on %s."), $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_43 =                     // line 217
$context["row"]) && is_array($__internal_compile_43) || $__internal_compile_43 instanceof ArrayAccess ? ($__internal_compile_43["Field"] ?? null) : null)))], "", false);
                    // line 218
                    yield "\">
                              ";
                    // line 219
                    yield PhpMyAdmin\Html\Generator::getIcon("b_ftext", _gettext("Fulltext"));
                    yield "
                            </a>
                        ";
                } else {
                    // line 222
                    yield "                            <span class=\"";
                    yield ((($context["hide_structure_actions"] ?? null)) ? ("dropdown-item-text") : ("nav-link px-1"));
                    yield " disabled\">";
                    yield PhpMyAdmin\Html\Generator::getIcon("bd_ftext", _gettext("Fulltext"));
                    yield "</span>
                        ";
                }
                // line 224
                yield "                        </li>

                        ";
                // line 227
                yield "                        <li class=\"";
                yield (( !($context["hide_structure_actions"] ?? null)) ? ("nav-item ") : (""));
                yield "browse text-nowrap\">
                            <a class=\"";
                // line 228
                yield ((($context["hide_structure_actions"] ?? null)) ? ("dropdown-item") : ("nav-link px-1"));
                yield "\" href=\"";
                yield PhpMyAdmin\Url::getFromRoute("/sql");
                yield "\" data-post=\"";
                yield PhpMyAdmin\Url::getCommon(["db" =>                 // line 229
($context["db"] ?? null), "table" =>                 // line 230
($context["table"] ?? null), "sql_query" => ((((((((("SELECT COUNT(*) AS " . PhpMyAdmin\Util::backquote(_gettext("Rows"))) . ", ") . PhpMyAdmin\Util::backquote((($__internal_compile_44 =                 // line 232
$context["row"]) && is_array($__internal_compile_44) || $__internal_compile_44 instanceof ArrayAccess ? ($__internal_compile_44["Field"] ?? null) : null))) . " FROM ") . PhpMyAdmin\Util::backquote(                // line 233
($context["table"] ?? null))) . " GROUP BY ") . PhpMyAdmin\Util::backquote((($__internal_compile_45 =                 // line 234
$context["row"]) && is_array($__internal_compile_45) || $__internal_compile_45 instanceof ArrayAccess ? ($__internal_compile_45["Field"] ?? null) : null))) . " ORDER BY ") . PhpMyAdmin\Util::backquote((($__internal_compile_46 =                 // line 235
$context["row"]) && is_array($__internal_compile_46) || $__internal_compile_46 instanceof ArrayAccess ? ($__internal_compile_46["Field"] ?? null) : null))), "is_browse_distinct" => true], "", false);
                // line 237
                yield "\">
                              ";
                // line 238
                yield PhpMyAdmin\Html\Generator::getIcon("b_browse", _gettext("Distinct values"));
                yield "
                            </a>
                        </li>
                        ";
                // line 241
                if ( !(null === CoreExtension::getAttribute($this->env, $this->source, ($context["relation_parameters"] ?? null), "centralColumnsFeature", [], "any", false, false, false, 241))) {
                    // line 242
                    yield "                            <li class=\"";
                    yield (( !($context["hide_structure_actions"] ?? null)) ? ("nav-item ") : (""));
                    yield "browse text-nowrap\">
                            ";
                    // line 243
                    if (CoreExtension::inFilter((($__internal_compile_47 = $context["row"]) && is_array($__internal_compile_47) || $__internal_compile_47 instanceof ArrayAccess ? ($__internal_compile_47["Field"] ?? null) : null), ($context["central_list"] ?? null))) {
                        // line 244
                        yield "                                <a class=\"";
                        yield ((($context["hide_structure_actions"] ?? null)) ? ("dropdown-item") : ("nav-link px-1"));
                        yield "\" href=\"";
                        yield PhpMyAdmin\Url::getFromRoute("/table/structure/central-columns-remove");
                        yield "\" data-post=\"";
                        yield PhpMyAdmin\Url::getCommon(["db" =>                         // line 245
($context["db"] ?? null), "table" =>                         // line 246
($context["table"] ?? null), "selected_fld" => [(($__internal_compile_48 =                         // line 247
$context["row"]) && is_array($__internal_compile_48) || $__internal_compile_48 instanceof ArrayAccess ? ($__internal_compile_48["Field"] ?? null) : null)]]);
                        // line 248
                        yield "\">
                                    ";
                        // line 249
                        yield PhpMyAdmin\Html\Generator::getIcon("centralColumns_delete", _gettext("Remove from central columns"));
                        yield "
                                </a>
                            ";
                    } else {
                        // line 252
                        yield "                                <a class=\"";
                        yield ((($context["hide_structure_actions"] ?? null)) ? ("dropdown-item") : ("nav-link px-1"));
                        yield "\" href=\"";
                        yield PhpMyAdmin\Url::getFromRoute("/table/structure/central-columns-add");
                        yield "\" data-post=\"";
                        yield PhpMyAdmin\Url::getCommon(["db" =>                         // line 253
($context["db"] ?? null), "table" =>                         // line 254
($context["table"] ?? null), "selected_fld" => [(($__internal_compile_49 =                         // line 255
$context["row"]) && is_array($__internal_compile_49) || $__internal_compile_49 instanceof ArrayAccess ? ($__internal_compile_49["Field"] ?? null) : null)]]);
                        // line 256
                        yield "\">
                                    ";
                        // line 257
                        yield PhpMyAdmin\Html\Generator::getIcon("centralColumns_add", _gettext("Add to central columns"));
                        yield "
                                </a>
                            ";
                    }
                    // line 260
                    yield "                            </li>
                        ";
                }
                // line 262
                yield "                  ";
                if ( !($context["hide_structure_actions"] ?? null)) {
                    // line 263
                    yield "                    </ul>
                  ";
                } else {
                    // line 265
                    yield "                    </ul>
                  </div>
                  ";
                }
                // line 268
                yield "                </td>
            ";
            }
            // line 270
            yield "        </tr>
        ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['row'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 272
        yield "        </tbody>
    </table>
    </div>
    <div class=\"d-print-none\">
        ";
        // line 276
        yield from         $this->loadTemplate("select_all.twig", "table/structure/display_structure.twig", 276)->unwrap()->yield(CoreExtension::toArray(["text_dir" =>         // line 277
($context["text_dir"] ?? null), "form_name" => "fieldsForm"]));
        // line 280
        yield "
        <button class=\"btn btn-link mult_submit\" type=\"submit\" formaction=\"";
        // line 281
        yield PhpMyAdmin\Url::getFromRoute("/table/structure/browse");
        yield "\">
          ";
        // line 282
        yield PhpMyAdmin\Html\Generator::getIcon("b_browse", _gettext("Browse"));
        yield "
        </button>

        ";
        // line 285
        if (( !($context["tbl_is_view"] ?? null) &&  !($context["db_is_system_schema"] ?? null))) {
            // line 286
            yield "          <button class=\"btn btn-link mult_submit\" type=\"submit\" formaction=\"";
            yield PhpMyAdmin\Url::getFromRoute("/table/structure/change");
            yield "\">
            ";
            // line 287
            yield PhpMyAdmin\Html\Generator::getIcon("b_edit", _gettext("Change"));
            yield "
          </button>
          <button class=\"btn btn-link mult_submit\" type=\"submit\" formaction=\"";
            // line 289
            yield PhpMyAdmin\Url::getFromRoute("/table/structure/drop-confirm");
            yield "\">
            ";
            // line 290
            yield PhpMyAdmin\Html\Generator::getIcon("b_drop", _gettext("Drop"));
            yield "
          </button>

          ";
            // line 293
            if ((($context["tbl_storage_engine"] ?? null) != "ARCHIVE")) {
                // line 294
                yield "            <button class=\"btn btn-link mult_submit\" type=\"submit\" formaction=\"";
                yield PhpMyAdmin\Url::getFromRoute("/table/structure/primary");
                yield "\">
              ";
                // line 295
                yield PhpMyAdmin\Html\Generator::getIcon("b_primary", _gettext("Primary"));
                yield "
            </button>
            <button class=\"btn btn-link mult_submit\" type=\"submit\" formaction=\"";
                // line 297
                yield PhpMyAdmin\Url::getFromRoute("/table/structure/unique");
                yield "\">
              ";
                // line 298
                yield PhpMyAdmin\Html\Generator::getIcon("b_unique", _gettext("Unique"));
                yield "
            </button>
            <button class=\"btn btn-link mult_submit\" type=\"submit\" formaction=\"";
                // line 300
                yield PhpMyAdmin\Url::getFromRoute("/table/structure/index");
                yield "\">
              ";
                // line 301
                yield PhpMyAdmin\Html\Generator::getIcon("b_index", _gettext("Index"));
                yield "
            </button>
            <button class=\"btn btn-link mult_submit\" type=\"submit\" formaction=\"";
                // line 303
                yield PhpMyAdmin\Url::getFromRoute("/table/structure/spatial");
                yield "\">
              ";
                // line 304
                yield PhpMyAdmin\Html\Generator::getIcon("b_spatial", _gettext("Spatial"));
                yield "
            </button>
            <button class=\"btn btn-link mult_submit\" type=\"submit\" formaction=\"";
                // line 306
                yield PhpMyAdmin\Url::getFromRoute("/table/structure/fulltext");
                yield "\">
              ";
                // line 307
                yield PhpMyAdmin\Html\Generator::getIcon("b_ftext", _gettext("Fulltext"));
                yield "
            </button>

            ";
                // line 310
                if ( !(null === CoreExtension::getAttribute($this->env, $this->source, ($context["relation_parameters"] ?? null), "centralColumnsFeature", [], "any", false, false, false, 310))) {
                    // line 311
                    yield "              <button class=\"btn btn-link mult_submit\" type=\"submit\" formaction=\"";
                    yield PhpMyAdmin\Url::getFromRoute("/table/structure/central-columns-add");
                    yield "\">
                ";
                    // line 312
                    yield PhpMyAdmin\Html\Generator::getIcon("centralColumns_add", _gettext("Add to central columns"));
                    yield "
              </button>
              <button class=\"btn btn-link mult_submit\" type=\"submit\" formaction=\"";
                    // line 314
                    yield PhpMyAdmin\Url::getFromRoute("/table/structure/central-columns-remove");
                    yield "\">
                ";
                    // line 315
                    yield PhpMyAdmin\Html\Generator::getIcon("centralColumns_delete", _gettext("Remove from central columns"));
                    yield "
              </button>
            ";
                }
                // line 318
                yield "          ";
            }
            // line 319
            yield "        ";
        }
        // line 320
        yield "    </div>
</form>
<hr class=\"d-print-none\">

<div class=\"modal fade\" id=\"moveColumnsModal\" tabindex=\"-1\" aria-labelledby=\"moveColumnsModalLabel\" aria-hidden=\"true\">
  <div class=\"modal-dialog\">
    <div class=\"modal-content\">
      <div class=\"modal-header\">
        <h5 class=\"modal-title\" id=\"moveColumnsModalLabel\">";
yield _gettext("Move columns");
        // line 328
        yield "</h5>
        <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\" aria-label=\"";
yield _gettext("Close");
        // line 329
        yield "\"></button>
      </div>
      <div class=\"modal-body\">
        <div id=\"move_columns_dialog\" title=\"";
yield _gettext("Move columns");
        // line 332
        yield "\">
          <p>";
yield _gettext("Move the columns by dragging them up and down.");
        // line 333
        yield "</p>
          <form action=\"";
        // line 334
        yield PhpMyAdmin\Url::getFromRoute("/table/structure/move-columns");
        yield "\" name=\"move_column_form\" id=\"move_column_form\">
            <div>
              ";
        // line 336
        yield PhpMyAdmin\Url::getHiddenInputs(($context["db"] ?? null), ($context["table"] ?? null));
        yield "
              <ul></ul>
            </div>
          </form>
        </div>
      </div>
      <div class=\"modal-footer\">
        <button type=\"button\" class=\"btn btn-primary\" id=\"designerModalGoButton\">";
yield _gettext("Go");
        // line 343
        yield "</button>
        <button type=\"button\" class=\"btn btn-secondary\" id=\"designerModalPreviewButton\" data-bs-target=\"#designerModalPreviewModal\" data-bs-toggle=\"modal\">";
yield _gettext("Preview SQL");
        // line 344
        yield "</button>
        <button type=\"button\" class=\"btn btn-secondary\" id=\"designerModalCloseButton\" data-bs-dismiss=\"modal\">";
yield _gettext("Close");
        // line 345
        yield "</button>
      </div>
    </div>
  </div>
</div>
<div class=\"modal fade\" id=\"designerModalPreviewModal\" aria-hidden=\"true\" aria-labelledby=\"designerModalPreviewModalLabel\" tabindex=\"-1\">
  <div class=\"modal-dialog\">
    <div class=\"modal-content\">
      <div class=\"modal-header\">
        <h5 class=\"modal-title\" id=\"designerModalPreviewModalLabel\">";
yield _gettext("Preview SQL");
        // line 354
        yield "</h5>
        <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\" aria-label=\"";
yield _gettext("Close");
        // line 355
        yield "\"></button>
      </div>
      <div class=\"modal-body\">
        <div class=\"spinner-border\" role=\"status\">
          <span class=\"visually-hidden\">";
yield _gettext("Loading…");
        // line 359
        yield "</span>
        </div>
      </div>
      <div class=\"modal-footer\">
        <button class=\"btn btn-primary\" data-bs-target=\"#moveColumnsModal\" data-bs-toggle=\"modal\">";
yield _gettext("Go back");
        // line 363
        yield "</button>
      </div>
    </div>
  </div>
</div>
<div class=\"modal fade\" id=\"moveColumnsErrorModal\" tabindex=\"-1\" aria-labelledby=\"moveColumnsErrorModalLabel\" aria-hidden=\"true\">
  <div class=\"modal-dialog\">
    <div class=\"modal-content\">
      <div class=\"modal-header\">
        <h5 class=\"modal-title\" id=\"moveColumnsErrorModalLabel\">";
yield _gettext("Error");
        // line 372
        yield "</h5>
        <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\" aria-label=\"";
yield _gettext("Close");
        // line 373
        yield "\"></button>
      </div>
      <div class=\"modal-body\">
      </div>
      <div class=\"modal-footer\">
        <button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">";
yield _gettext("OK");
        // line 378
        yield "</button>
      </div>
    </div>
  </div>
</div>

";
        // line 385
        yield "<div id=\"structure-action-links\" class=\"d-print-none\">
    ";
        // line 386
        if ((($context["tbl_is_view"] ?? null) &&  !($context["db_is_system_schema"] ?? null))) {
            // line 387
            yield "        ";
            yield PhpMyAdmin\Html\Generator::linkOrButton(PhpMyAdmin\Url::getFromRoute("/view/create"), ["db" =>             // line 389
($context["db"] ?? null), "table" => ($context["table"] ?? null)], PhpMyAdmin\Html\Generator::getIcon("b_edit", _gettext("Edit view"), true));
            // line 391
            yield "
    ";
        }
        // line 393
        yield "    <button type=\"button\" class=\"btn btn-link p-0 jsPrintButton\">";
        yield PhpMyAdmin\Html\Generator::getIcon("b_print", _gettext("Print"), true);
        yield "</button>
    ";
        // line 394
        if (( !($context["tbl_is_view"] ?? null) &&  !($context["db_is_system_schema"] ?? null))) {
            // line 395
            yield "        ";
            // line 396
            yield "        ";
            if (((($context["mysql_int_version"] ?? null) < 80000) || ($context["is_mariadb"] ?? null))) {
                // line 397
                yield "          <a class=\"me-0\" href=\"";
                yield PhpMyAdmin\Url::getFromRoute("/sql");
                yield "\" data-post=\"";
                yield PhpMyAdmin\Url::getCommon(["db" =>                 // line 398
($context["db"] ?? null), "table" =>                 // line 399
($context["table"] ?? null), "sql_query" => (("SELECT * FROM " . PhpMyAdmin\Util::backquote(                // line 400
($context["table"] ?? null))) . " PROCEDURE ANALYSE()"), "session_max_rows" => "all"], "", false);
                // line 402
                yield "\">
            ";
                // line 403
                yield PhpMyAdmin\Html\Generator::getIcon("b_tblanalyse", _gettext("Propose table structure"), true);
                // line 407
                yield "
          </a>
          ";
                // line 409
                yield PhpMyAdmin\Html\MySQLDocumentation::show("procedure_analyse");
                yield "
        ";
            }
            // line 411
            yield "        ";
            if (($context["is_active"] ?? null)) {
                // line 412
                yield "            <a href=\"";
                yield PhpMyAdmin\Url::getFromRoute("/table/tracking", ["db" => ($context["db"] ?? null), "table" => ($context["table"] ?? null)]);
                yield "\">
                ";
                // line 413
                yield PhpMyAdmin\Html\Generator::getIcon("eye", _gettext("Track table"), true);
                yield "
            </a>
        ";
            }
            // line 416
            yield "        <a href=\"#\" id=\"move_columns_anchor\">
            ";
            // line 417
            yield PhpMyAdmin\Html\Generator::getIcon("b_move", _gettext("Move columns"), true);
            yield "
        </a>
        <a href=\"";
            // line 419
            yield PhpMyAdmin\Url::getFromRoute("/normalization", ["db" => ($context["db"] ?? null), "table" => ($context["table"] ?? null)]);
            yield "\">
            ";
            // line 420
            yield PhpMyAdmin\Html\Generator::getIcon("normalize", _gettext("Normalize"), true);
            yield "
        </a>
    ";
        }
        // line 423
        yield "    ";
        if ((($context["tbl_is_view"] ?? null) &&  !($context["db_is_system_schema"] ?? null))) {
            // line 424
            yield "        ";
            if (($context["is_active"] ?? null)) {
                // line 425
                yield "            <a href=\"";
                yield PhpMyAdmin\Url::getFromRoute("/table/tracking", ["db" => ($context["db"] ?? null), "table" => ($context["table"] ?? null)]);
                yield "\">
                ";
                // line 426
                yield PhpMyAdmin\Html\Generator::getIcon("eye", _gettext("Track view"), true);
                yield "
            </a>
        ";
            }
            // line 429
            yield "    ";
        }
        // line 430
        yield "</div>
";
        // line 431
        if (( !($context["tbl_is_view"] ?? null) &&  !($context["db_is_system_schema"] ?? null))) {
            // line 432
            yield "    <form method=\"post\" action=\"";
            yield PhpMyAdmin\Url::getFromRoute("/table/add-field");
            yield "\" id=\"addColumns\" name=\"addColumns\" class=\"d-print-none\">
        ";
            // line 433
            yield PhpMyAdmin\Url::getHiddenInputs(($context["db"] ?? null), ($context["table"] ?? null));
            yield "
        ";
            // line 434
            if (PhpMyAdmin\Util::showIcons("ActionLinksMode")) {
                // line 435
                yield "            ";
                yield PhpMyAdmin\Html\Generator::getImage("b_insrow", _gettext("Add column"));
                yield "&nbsp;
        ";
            }
            // line 437
            yield "        ";
            $context["num_fields"] = ('' === $tmp = "<input type=\"number\" name=\"num_fields\" value=\"1\" onfocus=\"this.select()\" min=\"1\" required>") ? '' : new Markup($tmp, $this->env->getCharset());
            // line 440
            yield "        ";
            yield Twig\Extension\CoreExtension::sprintf(_gettext("Add %s column(s)"), ($context["num_fields"] ?? null));
            yield "
        <input type=\"hidden\" name=\"field_where\" value=\"after\">&nbsp;
        ";
            // line 443
            yield "        <select name=\"after_field\">
            <option value=\"first\" data-pos=\"first\">
                ";
yield _gettext("at beginning of table");
            // line 446
            yield "            </option>
            ";
            // line 447
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(($context["columns_list"] ?? null));
            $context['loop'] = [
              'parent' => $context['_parent'],
              'index0' => 0,
              'index'  => 1,
              'first'  => true,
            ];
            if (is_array($context['_seq']) || (is_object($context['_seq']) && $context['_seq'] instanceof \Countable)) {
                $length = count($context['_seq']);
                $context['loop']['revindex0'] = $length - 1;
                $context['loop']['revindex'] = $length;
                $context['loop']['length'] = $length;
                $context['loop']['last'] = 1 === $length;
            }
            foreach ($context['_seq'] as $context["_key"] => $context["one_column_name"]) {
                // line 448
                yield "                <option value=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($context["one_column_name"], "html", null, true);
                yield "\"";
                // line 449
                yield (((CoreExtension::getAttribute($this->env, $this->source, $context["loop"], "revindex0", [], "any", false, false, false, 449) == 0)) ? (" selected=\"selected\"") : (""));
                yield ">
                    ";
                // line 450
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(Twig\Extension\CoreExtension::sprintf(_gettext("after %s"), $context["one_column_name"]), "html", null, true);
                yield "
                </option>
            ";
                ++$context['loop']['index0'];
                ++$context['loop']['index'];
                $context['loop']['first'] = false;
                if (isset($context['loop']['length'])) {
                    --$context['loop']['revindex0'];
                    --$context['loop']['revindex'];
                    $context['loop']['last'] = 0 === $context['loop']['revindex0'];
                }
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['one_column_name'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 453
            yield "        </select>
        <input class=\"btn btn-primary\" type=\"submit\" value=\"";
yield _gettext("Go");
            // line 454
            yield "\">
    </form>
";
        }
        // line 457
        yield "
";
        // line 458
        if ((( !($context["tbl_is_view"] ?? null) &&  !($context["db_is_system_schema"] ?? null)) && (($context["tbl_storage_engine"] ?? null) != "ARCHIVE"))) {
            // line 459
            yield "  <div id=\"index_div\" class=\"w-100 ajax\">
    <fieldset class=\"pma-fieldset index_info\">
      <legend id=\"index_header\">
        ";
yield _gettext("Indexes");
            // line 463
            yield "        ";
            yield PhpMyAdmin\Html\MySQLDocumentation::show("optimizing-database-structure");
            yield "
      </legend>

      ";
            // line 466
            if ( !Twig\Extension\CoreExtension::testEmpty(($context["indexes"] ?? null))) {
                // line 467
                yield "        ";
                yield ($context["indexes_duplicates"] ?? null);
                yield "

        ";
                // line 469
                yield Twig\Extension\CoreExtension::include($this->env, $context, "modals/preview_sql_confirmation.twig");
                yield "
        <div class=\"table-responsive jsresponsive\">
          <table class=\"table table-striped table-hover table-sm w-auto align-middle\" id=\"table_index\">
            <thead>
              <tr>
                <th colspan=\"3\" class=\"d-print-none\">";
yield _gettext("Action");
                // line 474
                yield "</th>
                <th>";
yield _gettext("Keyname");
                // line 475
                yield "</th>
                <th>";
yield _gettext("Type");
                // line 476
                yield "</th>
                <th>";
yield _gettext("Unique");
                // line 477
                yield "</th>
                <th>";
yield _gettext("Packed");
                // line 478
                yield "</th>
                <th>";
yield _gettext("Column");
                // line 479
                yield "</th>
                <th>";
yield _gettext("Cardinality");
                // line 480
                yield "</th>
                <th>";
yield _gettext("Collation");
                // line 481
                yield "</th>
                <th>";
yield _gettext("Null");
                // line 482
                yield "</th>
                <th>";
yield _gettext("Comment");
                // line 483
                yield "</th>
              </tr>
            </thead>

          <tbody class=\"row_span\">
            ";
                // line 488
                $context['_parent'] = $context;
                $context['_seq'] = CoreExtension::ensureTraversable(($context["indexes"] ?? null));
                foreach ($context['_seq'] as $context["_key"] => $context["index"]) {
                    // line 489
                    yield "                ";
                    $context["columns_count"] = CoreExtension::getAttribute($this->env, $this->source, $context["index"], "getColumnCount", [], "method", false, false, false, 489);
                    // line 490
                    yield "                <tr class=\"noclick\">
                <td rowspan=\"";
                    // line 491
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["columns_count"] ?? null), "html", null, true);
                    yield "\" class=\"edit_index d-print-none ajax\">
                  <a class=\"ajax\" href=\"";
                    // line 492
                    yield PhpMyAdmin\Url::getFromRoute("/table/indexes");
                    yield "\" data-post=\"";
                    yield PhpMyAdmin\Url::getCommon(["db" =>                     // line 493
($context["db"] ?? null), "table" =>                     // line 494
($context["table"] ?? null), "index" => CoreExtension::getAttribute($this->env, $this->source,                     // line 495
$context["index"], "getName", [], "method", false, false, false, 495)], "", false);
                    // line 496
                    yield "\">
                    ";
                    // line 497
                    yield PhpMyAdmin\Html\Generator::getIcon("b_edit", _gettext("Edit"));
                    yield "
                  </a>
                </td>
                <td rowspan=\"";
                    // line 500
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["columns_count"] ?? null), "html", null, true);
                    yield "\" class=\"rename_index d-print-none ajax\" >
                  <a class=\"ajax\" href=\"";
                    // line 501
                    yield PhpMyAdmin\Url::getFromRoute("/table/indexes/rename");
                    yield "\" data-post=\"";
                    yield PhpMyAdmin\Url::getCommon(["db" =>                     // line 502
($context["db"] ?? null), "table" =>                     // line 503
($context["table"] ?? null), "index" => CoreExtension::getAttribute($this->env, $this->source,                     // line 504
$context["index"], "getName", [], "method", false, false, false, 504)], "", false);
                    // line 505
                    yield "\">
                    ";
                    // line 506
                    yield PhpMyAdmin\Html\Generator::getIcon("b_rename", _gettext("Rename"));
                    yield "
                  </a>
                </td>
                <td rowspan=\"";
                    // line 509
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["columns_count"] ?? null), "html", null, true);
                    yield "\" class=\"d-print-none\">
                  ";
                    // line 510
                    if ((CoreExtension::getAttribute($this->env, $this->source, $context["index"], "getName", [], "method", false, false, false, 510) == "PRIMARY")) {
                        // line 511
                        yield "                    ";
                        $context["index_params"] = ["sql_query" => (("ALTER TABLE " . PhpMyAdmin\Util::backquote(                        // line 512
($context["table"] ?? null))) . " DROP PRIMARY KEY;"), "message_to_show" => _gettext("The primary key has been dropped.")];
                        // line 515
                        yield "                  ";
                    } else {
                        // line 516
                        yield "                    ";
                        $context["index_params"] = ["sql_query" => (((("ALTER TABLE " . PhpMyAdmin\Util::backquote(                        // line 517
($context["table"] ?? null))) . " DROP INDEX ") . PhpMyAdmin\Util::backquote(CoreExtension::getAttribute($this->env, $this->source, $context["index"], "getName", [], "method", false, false, false, 517))) . ";"), "message_to_show" => Twig\Extension\CoreExtension::sprintf(_gettext("Index %s has been dropped."), CoreExtension::getAttribute($this->env, $this->source,                         // line 518
$context["index"], "getName", [], "method", false, false, false, 518))];
                        // line 520
                        yield "                  ";
                    }
                    // line 521
                    yield "
                  <input type=\"hidden\" class=\"drop_primary_key_index_msg\" value=\"";
                    // line 522
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["index_params"] ?? null), "sql_query", [], "any", false, false, false, 522), "html", null, true);
                    yield "\">
                  ";
                    // line 523
                    yield PhpMyAdmin\Html\Generator::linkOrButton(PhpMyAdmin\Url::getFromRoute("/sql"), Twig\Extension\CoreExtension::merge(                    // line 525
($context["index_params"] ?? null), ["db" => ($context["db"] ?? null), "table" => ($context["table"] ?? null)]), PhpMyAdmin\Html\Generator::getIcon("b_drop", _gettext("Drop")), ["class" => "drop_primary_key_index_anchor ajax"]);
                    // line 528
                    yield "
                </td>
                <th rowspan=\"";
                    // line 530
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["columns_count"] ?? null), "html", null, true);
                    yield "\">";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["index"], "getName", [], "method", false, false, false, 530), "html", null, true);
                    yield "</th>
                <td rowspan=\"";
                    // line 531
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["columns_count"] ?? null), "html", null, true);
                    yield "\">";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(((CoreExtension::getAttribute($this->env, $this->source, $context["index"], "getType", [], "method", true, true, false, 531)) ? (Twig\Extension\CoreExtension::default(CoreExtension::getAttribute($this->env, $this->source, $context["index"], "getType", [], "method", false, false, false, 531), CoreExtension::getAttribute($this->env, $this->source, $context["index"], "getChoice", [], "method", false, false, false, 531))) : (CoreExtension::getAttribute($this->env, $this->source, $context["index"], "getChoice", [], "method", false, false, false, 531))), "html", null, true);
                    yield "</td>
                <td rowspan=\"";
                    // line 532
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["columns_count"] ?? null), "html", null, true);
                    yield "\">";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(((CoreExtension::getAttribute($this->env, $this->source, $context["index"], "isUnique", [], "method", false, false, false, 532)) ? (_gettext("Yes")) : (_gettext("No"))), "html", null, true);
                    yield "</td>
                <td rowspan=\"";
                    // line 533
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["columns_count"] ?? null), "html", null, true);
                    yield "\">";
                    yield CoreExtension::getAttribute($this->env, $this->source, $context["index"], "isPacked", [], "method", false, false, false, 533);
                    yield "</td>

                ";
                    // line 535
                    $context['_parent'] = $context;
                    $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, $context["index"], "getColumns", [], "method", false, false, false, 535));
                    foreach ($context['_seq'] as $context["_key"] => $context["column"]) {
                        // line 536
                        yield "                  ";
                        if ((CoreExtension::getAttribute($this->env, $this->source, $context["column"], "getSeqInIndex", [], "method", false, false, false, 536) > 1)) {
                            // line 537
                            yield "                    <tr class=\"noclick\">
                  ";
                        }
                        // line 539
                        yield "                  <td>
                    ";
                        // line 540
                        if (CoreExtension::getAttribute($this->env, $this->source, $context["column"], "hasExpression", [], "method", false, false, false, 540)) {
                            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["column"], "getExpression", [], "method", false, false, false, 540), "html", null, true);
                        } else {
                            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["column"], "getName", [], "method", false, false, false, 540), "html", null, true);
                        }
                        // line 541
                        yield "                    ";
                        if ( !Twig\Extension\CoreExtension::testEmpty(CoreExtension::getAttribute($this->env, $this->source, $context["column"], "getSubPart", [], "method", false, false, false, 541))) {
                            // line 542
                            yield "                      (";
                            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["column"], "getSubPart", [], "method", false, false, false, 542), "html", null, true);
                            yield ")
                    ";
                        }
                        // line 544
                        yield "                  </td>
                  <td>";
                        // line 545
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["column"], "getCardinality", [], "method", false, false, false, 545), "html", null, true);
                        yield "</td>
                  <td>";
                        // line 546
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["column"], "getCollation", [], "method", false, false, false, 546), "html", null, true);
                        yield "</td>
                  <td>";
                        // line 547
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["column"], "getNull", [true], "method", false, false, false, 547), "html", null, true);
                        yield "</td>

                  ";
                        // line 549
                        if ((CoreExtension::getAttribute($this->env, $this->source, $context["column"], "getSeqInIndex", [], "method", false, false, false, 549) == 1)) {
                            // line 550
                            yield "                    <td rowspan=\"";
                            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["columns_count"] ?? null), "html", null, true);
                            yield "\">";
                            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["index"], "getComments", [], "method", false, false, false, 550), "html", null, true);
                            yield "</td>
                  ";
                        }
                        // line 552
                        yield "                  </tr>
                ";
                    }
                    $_parent = $context['_parent'];
                    unset($context['_seq'], $context['_iterated'], $context['_key'], $context['column'], $context['_parent'], $context['loop']);
                    $context = array_intersect_key($context, $_parent) + $_parent;
                    // line 554
                    yield "              ";
                }
                $_parent = $context['_parent'];
                unset($context['_seq'], $context['_iterated'], $context['_key'], $context['index'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 555
                yield "            </tbody>
          </table>
        </div>
      ";
            } else {
                // line 559
                yield "        <div class=\"no_indexes_defined\">";
                yield $this->env->getFilter('notice')->getCallable()(_gettext("No index defined!"));
                yield "</div>
      ";
            }
            // line 561
            yield "    </fieldset>

    <fieldset class=\"pma-fieldset tblFooters d-print-none text-start\">
      <form action=\"";
            // line 564
            yield PhpMyAdmin\Url::getFromRoute("/table/indexes");
            yield "\" method=\"post\">
        ";
            // line 565
            yield PhpMyAdmin\Url::getHiddenInputs(($context["db"] ?? null), ($context["table"] ?? null));
            yield "
        <input type=\"hidden\" name=\"create_index\" value=\"1\">

        ";
            // line 568
            $___internal_parse_0_ = ('' === $tmp = \Twig\Extension\CoreExtension::captureOutput((function () use (&$context, $macros, $blocks) {
                // line 569
                yield "          ";
yield _gettext("Create an index on %s columns");
                // line 570
                yield "        ";
                return; yield '';
            })())) ? '' : new Markup($tmp, $this->env->getCharset());
            // line 568
            yield Twig\Extension\CoreExtension::sprintf($___internal_parse_0_, "<input class=\"mx-2\" type=\"number\" name=\"added_fields\" value=\"1\" min=\"1\" max=\"16\" required>");
            // line 571
            yield "
        <input class=\"btn btn-primary add_index ajax\" type=\"submit\" value=\"";
yield _gettext("Go");
            // line 572
            yield "\">
      </form>
    </fieldset>
  </div>
  ";
            // line 576
            yield Twig\Extension\CoreExtension::include($this->env, $context, "modals/index_dialog_modal.twig");
            yield "
";
        }
        // line 578
        yield "
";
        // line 580
        if (($context["have_partitioning"] ?? null)) {
            // line 581
            yield "    ";
            // line 582
            yield "    ";
            if (( !Twig\Extension\CoreExtension::testEmpty(($context["partition_names"] ?? null)) &&  !(null === (($__internal_compile_50 = ($context["partition_names"] ?? null)) && is_array($__internal_compile_50) || $__internal_compile_50 instanceof ArrayAccess ? ($__internal_compile_50[0] ?? null) : null)))) {
                // line 583
                yield "        ";
                $context["first_partition"] = (($__internal_compile_51 = ($context["partitions"] ?? null)) && is_array($__internal_compile_51) || $__internal_compile_51 instanceof ArrayAccess ? ($__internal_compile_51[0] ?? null) : null);
                // line 584
                yield "        ";
                $context["range_or_list"] = ((((CoreExtension::getAttribute($this->env, $this->source, ($context["first_partition"] ?? null), "getMethod", [], "method", false, false, false, 584) == "RANGE") || (CoreExtension::getAttribute($this->env, $this->source,                 // line 585
($context["first_partition"] ?? null), "getMethod", [], "method", false, false, false, 585) == "RANGE COLUMNS")) || (CoreExtension::getAttribute($this->env, $this->source,                 // line 586
($context["first_partition"] ?? null), "getMethod", [], "method", false, false, false, 586) == "LIST")) || (CoreExtension::getAttribute($this->env, $this->source,                 // line 587
($context["first_partition"] ?? null), "getMethod", [], "method", false, false, false, 587) == "LIST COLUMNS"));
                // line 588
                yield "        ";
                $context["sub_partitions"] = CoreExtension::getAttribute($this->env, $this->source, ($context["first_partition"] ?? null), "getSubPartitions", [], "method", false, false, false, 588);
                // line 589
                yield "        ";
                $context["has_sub_partitions"] = CoreExtension::getAttribute($this->env, $this->source, ($context["first_partition"] ?? null), "hasSubPartitions", [], "method", false, false, false, 589);
                // line 590
                yield "        ";
                if (($context["has_sub_partitions"] ?? null)) {
                    // line 591
                    yield "            ";
                    $context["first_sub_partition"] = (($__internal_compile_52 = ($context["sub_partitions"] ?? null)) && is_array($__internal_compile_52) || $__internal_compile_52 instanceof ArrayAccess ? ($__internal_compile_52[0] ?? null) : null);
                    // line 592
                    yield "        ";
                }
                // line 593
                yield "
        ";
                // line 594
                if ((($context["default_sliders_state"] ?? null) != "disabled")) {
                    // line 595
                    yield "        <div class=\"mb-3\">
          <button class=\"btn btn-sm btn-secondary\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#partitionsCollapse\" aria-expanded=\"";
                    // line 596
                    yield (((($context["default_sliders_state"] ?? null) == "open")) ? ("true") : ("false"));
                    yield "\" aria-controls=\"partitionsCollapse\">
            ";
yield _gettext("Partitions");
                    // line 598
                    yield "          </button>
        </div>
        <div class=\"collapse mb-3";
                    // line 600
                    yield (((($context["default_sliders_state"] ?? null) == "open")) ? (" show") : (""));
                    yield "\" id=\"partitionsCollapse\">
        ";
                }
                // line 602
                yield "
        ";
                // line 603
                yield from                 $this->loadTemplate("table/structure/display_partitions.twig", "table/structure/display_structure.twig", 603)->unwrap()->yield(CoreExtension::toArray(["db" =>                 // line 604
($context["db"] ?? null), "table" =>                 // line 605
($context["table"] ?? null), "partitions" =>                 // line 606
($context["partitions"] ?? null), "partition_method" => CoreExtension::getAttribute($this->env, $this->source,                 // line 607
($context["first_partition"] ?? null), "getMethod", [], "method", false, false, false, 607), "partition_expression" => CoreExtension::getAttribute($this->env, $this->source,                 // line 608
($context["first_partition"] ?? null), "getExpression", [], "method", false, false, false, 608), "has_description" =>  !Twig\Extension\CoreExtension::testEmpty(CoreExtension::getAttribute($this->env, $this->source,                 // line 609
($context["first_partition"] ?? null), "getDescription", [], "method", false, false, false, 609)), "has_sub_partitions" =>                 // line 610
($context["has_sub_partitions"] ?? null), "sub_partition_method" => ((                // line 611
($context["has_sub_partitions"] ?? null)) ? (CoreExtension::getAttribute($this->env, $this->source, ($context["first_sub_partition"] ?? null), "getMethod", [], "method", false, false, false, 611)) : ("")), "sub_partition_expression" => ((                // line 612
($context["has_sub_partitions"] ?? null)) ? (CoreExtension::getAttribute($this->env, $this->source, ($context["first_sub_partition"] ?? null), "getExpression", [], "method", false, false, false, 612)) : ("")), "range_or_list" =>                 // line 613
($context["range_or_list"] ?? null)]));
                // line 615
                yield "    ";
            } else {
                // line 616
                yield "        ";
                yield from                 $this->loadTemplate("table/structure/display_partitions.twig", "table/structure/display_structure.twig", 616)->unwrap()->yield(CoreExtension::toArray(["db" =>                 // line 617
($context["db"] ?? null), "table" =>                 // line 618
($context["table"] ?? null)]));
                // line 620
                yield "    ";
            }
            // line 621
            yield "    ";
            if ((($context["default_sliders_state"] ?? null) != "disabled")) {
                // line 622
                yield "    </div>
    ";
            }
        }
        // line 625
        yield "
";
        // line 627
        if (($context["show_stats"] ?? null)) {
            // line 628
            yield "    ";
            yield ($context["table_stats"] ?? null);
            yield "
";
        }
        // line 630
        yield "<div class=\"clearfloat\"></div>
";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "table/structure/display_structure.twig";
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
        return array (  1494 => 630,  1488 => 628,  1486 => 627,  1483 => 625,  1478 => 622,  1475 => 621,  1472 => 620,  1470 => 618,  1469 => 617,  1467 => 616,  1464 => 615,  1462 => 613,  1461 => 612,  1460 => 611,  1459 => 610,  1458 => 609,  1457 => 608,  1456 => 607,  1455 => 606,  1454 => 605,  1453 => 604,  1452 => 603,  1449 => 602,  1444 => 600,  1440 => 598,  1435 => 596,  1432 => 595,  1430 => 594,  1427 => 593,  1424 => 592,  1421 => 591,  1418 => 590,  1415 => 589,  1412 => 588,  1410 => 587,  1409 => 586,  1408 => 585,  1406 => 584,  1403 => 583,  1400 => 582,  1398 => 581,  1396 => 580,  1393 => 578,  1388 => 576,  1382 => 572,  1378 => 571,  1376 => 568,  1372 => 570,  1369 => 569,  1367 => 568,  1361 => 565,  1357 => 564,  1352 => 561,  1346 => 559,  1340 => 555,  1334 => 554,  1327 => 552,  1319 => 550,  1317 => 549,  1312 => 547,  1308 => 546,  1304 => 545,  1301 => 544,  1295 => 542,  1292 => 541,  1286 => 540,  1283 => 539,  1279 => 537,  1276 => 536,  1272 => 535,  1265 => 533,  1259 => 532,  1253 => 531,  1247 => 530,  1243 => 528,  1241 => 525,  1240 => 523,  1236 => 522,  1233 => 521,  1230 => 520,  1228 => 518,  1227 => 517,  1225 => 516,  1222 => 515,  1220 => 512,  1218 => 511,  1216 => 510,  1212 => 509,  1206 => 506,  1203 => 505,  1201 => 504,  1200 => 503,  1199 => 502,  1196 => 501,  1192 => 500,  1186 => 497,  1183 => 496,  1181 => 495,  1180 => 494,  1179 => 493,  1176 => 492,  1172 => 491,  1169 => 490,  1166 => 489,  1162 => 488,  1155 => 483,  1151 => 482,  1147 => 481,  1143 => 480,  1139 => 479,  1135 => 478,  1131 => 477,  1127 => 476,  1123 => 475,  1119 => 474,  1110 => 469,  1104 => 467,  1102 => 466,  1095 => 463,  1089 => 459,  1087 => 458,  1084 => 457,  1079 => 454,  1075 => 453,  1058 => 450,  1054 => 449,  1050 => 448,  1033 => 447,  1030 => 446,  1025 => 443,  1019 => 440,  1016 => 437,  1010 => 435,  1008 => 434,  1004 => 433,  999 => 432,  997 => 431,  994 => 430,  991 => 429,  985 => 426,  980 => 425,  977 => 424,  974 => 423,  968 => 420,  964 => 419,  959 => 417,  956 => 416,  950 => 413,  945 => 412,  942 => 411,  937 => 409,  933 => 407,  931 => 403,  928 => 402,  926 => 400,  925 => 399,  924 => 398,  920 => 397,  917 => 396,  915 => 395,  913 => 394,  908 => 393,  904 => 391,  902 => 389,  900 => 387,  898 => 386,  895 => 385,  887 => 378,  879 => 373,  875 => 372,  863 => 363,  856 => 359,  849 => 355,  845 => 354,  833 => 345,  829 => 344,  825 => 343,  814 => 336,  809 => 334,  806 => 333,  802 => 332,  796 => 329,  792 => 328,  781 => 320,  778 => 319,  775 => 318,  769 => 315,  765 => 314,  760 => 312,  755 => 311,  753 => 310,  747 => 307,  743 => 306,  738 => 304,  734 => 303,  729 => 301,  725 => 300,  720 => 298,  716 => 297,  711 => 295,  706 => 294,  704 => 293,  698 => 290,  694 => 289,  689 => 287,  684 => 286,  682 => 285,  676 => 282,  672 => 281,  669 => 280,  667 => 277,  666 => 276,  660 => 272,  653 => 270,  649 => 268,  644 => 265,  640 => 263,  637 => 262,  633 => 260,  627 => 257,  624 => 256,  622 => 255,  621 => 254,  620 => 253,  614 => 252,  608 => 249,  605 => 248,  603 => 247,  602 => 246,  601 => 245,  595 => 244,  593 => 243,  588 => 242,  586 => 241,  580 => 238,  577 => 237,  575 => 235,  574 => 234,  573 => 233,  572 => 232,  571 => 230,  570 => 229,  565 => 228,  560 => 227,  556 => 224,  548 => 222,  542 => 219,  539 => 218,  537 => 217,  536 => 216,  535 => 215,  534 => 214,  528 => 213,  526 => 212,  525 => 211,  524 => 210,  523 => 209,  522 => 208,  521 => 207,  516 => 206,  512 => 203,  506 => 200,  503 => 199,  501 => 198,  500 => 197,  499 => 196,  498 => 195,  492 => 194,  484 => 192,  482 => 191,  477 => 190,  475 => 180,  471 => 178,  465 => 175,  462 => 174,  460 => 173,  459 => 172,  458 => 171,  457 => 170,  451 => 169,  443 => 167,  441 => 166,  437 => 165,  433 => 163,  427 => 160,  424 => 159,  422 => 158,  421 => 157,  420 => 156,  419 => 155,  413 => 154,  405 => 152,  403 => 151,  399 => 150,  395 => 148,  389 => 145,  386 => 144,  384 => 143,  383 => 142,  382 => 141,  381 => 140,  375 => 139,  367 => 137,  365 => 136,  360 => 135,  356 => 133,  351 => 130,  347 => 129,  345 => 128,  342 => 127,  339 => 126,  337 => 125,  334 => 124,  327 => 120,  324 => 119,  322 => 118,  321 => 116,  320 => 115,  319 => 114,  318 => 113,  315 => 112,  308 => 108,  305 => 107,  303 => 105,  302 => 104,  301 => 103,  300 => 102,  297 => 101,  295 => 100,  290 => 99,  284 => 96,  281 => 95,  279 => 94,  276 => 93,  271 => 91,  268 => 89,  266 => 88,  263 => 86,  260 => 84,  258 => 83,  256 => 82,  252 => 80,  248 => 79,  245 => 78,  237 => 76,  235 => 75,  230 => 72,  222 => 70,  220 => 69,  219 => 68,  215 => 67,  210 => 65,  203 => 62,  197 => 60,  189 => 58,  187 => 57,  183 => 56,  178 => 54,  171 => 52,  166 => 49,  164 => 48,  161 => 47,  159 => 46,  156 => 45,  154 => 44,  151 => 43,  148 => 42,  143 => 41,  140 => 40,  135 => 36,  129 => 34,  126 => 33,  123 => 32,  118 => 30,  113 => 28,  111 => 27,  108 => 26,  104 => 25,  100 => 24,  96 => 23,  92 => 22,  88 => 21,  81 => 17,  76 => 13,  73 => 12,  70 => 10,  68 => 9,  66 => 8,  64 => 7,  60 => 5,  56 => 4,  51 => 3,  47 => 2,  36 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "table/structure/display_structure.twig", "/home/oliverbob/ginto/vendor/phpmyadmin/phpmyadmin/templates/table/structure/display_structure.twig");
    }
}
