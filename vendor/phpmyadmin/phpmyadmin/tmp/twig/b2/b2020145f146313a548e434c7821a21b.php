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

/* table/insert/column_row.twig */
class __TwigTemplate_b75431627e0bcc225a704564ccbca889 extends Template
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
        yield "<tr class=\"noclick\">
  <td class=\"text-center\">
    ";
        // line 3
        yield CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_title", [], "any", false, false, false, 3);
        yield "
    <input type=\"hidden\" name=\"fields_name[multi_edit][";
        // line 4
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
        yield "][";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 4), "html", null, true);
        yield "]\" value=\"";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field", [], "any", false, false, false, 4), "html", null, true);
        yield "\">
  </td>

  ";
        // line 7
        if (($context["show_field_types_in_data_edit_view"] ?? null)) {
            // line 8
            yield "    <td class=\"text-center";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "wrap", [], "any", false, false, false, 8), "html", null, true);
            yield "\">
      <span class=\"column_type\" dir=\"ltr\">";
            // line 9
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "pma_type", [], "any", false, false, false, 9), "html", null, true);
            yield "</span>
    </td>
  ";
        }
        // line 12
        yield "
  ";
        // line 13
        if (($context["show_function_fields"] ?? null)) {
            // line 14
            yield "    ";
            if (($context["is_column_binary"] ?? null)) {
                // line 15
                yield "      <td class=\"text-center\">";
yield _gettext("Binary");
                yield "</td>
    ";
            } elseif ((CoreExtension::inFilter("enum", CoreExtension::getAttribute($this->env, $this->source,             // line 16
($context["column"] ?? null), "True_Type", [], "any", false, false, false, 16)) || CoreExtension::inFilter("set", CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "True_Type", [], "any", false, false, false, 16)))) {
                // line 17
                yield "      <td class=\"text-center\">--</td>
    ";
            } else {
                // line 19
                yield "      <td>
        <select name=\"funcs[multi_edit][";
                // line 20
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
                yield "][";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 20), "html", null, true);
                yield "]\" onchange=\"return verificationsAfterFieldChange('";
                yield PhpMyAdmin\Sanitize::escapeJsString(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 20));
                yield "', '";
                yield PhpMyAdmin\Sanitize::escapeJsString(($context["row_id"] ?? null));
                yield "', '";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "pma_type", [], "any", false, false, false, 20), "html", null, true);
                yield "')\" id=\"field_";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["id_index"] ?? null), "html", null, true);
                yield "_1\">
          ";
                // line 21
                yield ($context["function_options"] ?? null);
                yield "
        </select>
      </td>
    ";
            }
            // line 25
            yield "  ";
        }
        // line 26
        yield "
  <td>
    ";
        // line 28
        if (((Twig\Extension\CoreExtension::upper($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Null", [], "any", false, false, false, 28)) == "YES") &&  !($context["read_only"] ?? null))) {
            // line 29
            yield "      <input type=\"hidden\" name=\"fields_null_prev[multi_edit][";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
            yield "][";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 29), "html", null, true);
            yield "]\"";
            yield (((($context["real_null_value"] ?? null) &&  !CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "first_timestamp", [], "any", false, false, false, 29))) ? (" value=\"on\"") : (""));
            yield ">
      <input type=\"checkbox\" class=\"checkbox_null\" name=\"fields_null[multi_edit][";
            // line 30
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
            yield "][";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 30), "html", null, true);
            yield "]\" id=\"field_";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["id_index"] ?? null), "html", null, true);
            yield "_2\" aria-label=\"";
yield _gettext("Use the NULL value for this column.");
            yield "\"";
            yield ((($context["real_null_value"] ?? null)) ? (" checked") : (""));
            yield ">
      <input type=\"hidden\" class=\"nullify_code\" name=\"nullify_code[multi_edit][";
            // line 31
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
            yield "][";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 31), "html", null, true);
            yield "]\" value=\"";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["nullify_code"] ?? null), "html", null, true);
            yield "\">
      <input type=\"hidden\" class=\"hashed_field\" name=\"hashed_field[multi_edit][";
            // line 32
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
            yield "][";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 32), "html", null, true);
            yield "]\" value=\"";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 32), "html", null, true);
            yield "\">
      <input type=\"hidden\" class=\"multi_edit\" name=\"multi_edit[multi_edit][";
            // line 33
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
            yield "][";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 33), "html", null, true);
            yield "]\" value=\"";
            yield PhpMyAdmin\Sanitize::escapeJsString((("[multi_edit][" . ($context["row_id"] ?? null)) . "]"));
            yield "\">
    ";
        }
        // line 35
        yield "  </td>

  <td data-type=\"";
        // line 37
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["type"] ?? null), "html", null, true);
        yield "\" data-decimals=\"";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["decimals"] ?? null), "html", null, true);
        yield "\">
    ";
        // line 39
        yield "    <span class=\"default_value hide\">";
        yield ($context["special_chars"] ?? null);
        yield "</span>

    ";
        // line 41
        if ( !Twig\Extension\CoreExtension::testEmpty(($context["transformed_value"] ?? null))) {
            // line 42
            yield "      ";
            yield ($context["transformed_value"] ?? null);
            yield "
    ";
        } else {
            // line 44
            yield "      ";
            if (($context["is_value_foreign_link"] ?? null)) {
                // line 45
                yield "        ";
                yield ($context["backup_field"] ?? null);
                yield "
        <input type=\"hidden\" name=\"fields_type[multi_edit][";
                // line 46
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
                yield "][";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 46), "html", null, true);
                yield "]\" value=\"foreign\">
        <input type=\"text\" name=\"fields[multi_edit][";
                // line 47
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
                yield "][";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 47), "html", null, true);
                yield "]\" class=\"textfield\" tabindex=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($context["tab_index"] ?? null) + ($context["tab_index_for_value"] ?? null)), "html", null, true);
                yield "\" onchange=\"return verificationsAfterFieldChange('";
                yield PhpMyAdmin\Sanitize::escapeJsString(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 47));
                yield "', '";
                yield PhpMyAdmin\Sanitize::escapeJsString(($context["row_id"] ?? null));
                yield "', '";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "pma_type", [], "any", false, false, false, 47), "html", null, true);
                yield "')\" id=\"field_";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["id_index"] ?? null), "html", null, true);
                yield "_3\" value=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["data"] ?? null), "html", null, true);
                yield "\">
        <a class=\"ajax browse_foreign\" href=\"";
                // line 48
                yield PhpMyAdmin\Url::getFromRoute("/browse-foreigners");
                yield "\" data-post=\"";
                yield PhpMyAdmin\Url::getCommon(["db" => ($context["db"] ?? null), "table" => ($context["table"] ?? null), "field" => CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field", [], "any", false, false, false, 48), "rownumber" => ($context["row_id"] ?? null), "data" => ($context["data"] ?? null)]);
                yield "\">";
                yield PhpMyAdmin\Html\Generator::getIcon("b_browse", _gettext("Browse foreign values"));
                yield "</a>
      ";
            } elseif ( !Twig\Extension\CoreExtension::testEmpty(            // line 49
($context["foreign_dropdown"] ?? null))) {
                // line 50
                yield "        ";
                yield ($context["backup_field"] ?? null);
                yield "
        <input type=\"hidden\" name=\"fields_type[multi_edit][";
                // line 51
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
                yield "][";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 51), "html", null, true);
                yield "]\" value=\"";
                yield ((CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "is_binary", [], "any", false, false, false, 51)) ? ("hex") : ("foreign"));
                yield "\">
        <select name=\"fields[multi_edit][";
                // line 52
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
                yield "][";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 52), "html", null, true);
                yield "]\" class=\"textfield\" tabindex=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($context["tab_index"] ?? null) + ($context["tab_index_for_value"] ?? null)), "html", null, true);
                yield "\" id=\"field_";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["id_index"] ?? null), "html", null, true);
                yield "_3\" onchange=\"return verificationsAfterFieldChange('";
                yield PhpMyAdmin\Sanitize::escapeJsString(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 52));
                yield "', '";
                yield PhpMyAdmin\Sanitize::escapeJsString(($context["row_id"] ?? null));
                yield "', '";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "pma_type", [], "any", false, false, false, 52), "html", null, true);
                yield "')\">
          ";
                // line 53
                yield ($context["foreign_dropdown"] ?? null);
                yield "
        </select>
      ";
            } elseif ((((            // line 55
($context["longtext_double_textarea"] ?? null) && CoreExtension::inFilter("longtext", CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "pma_type", [], "any", false, false, false, 55))) || CoreExtension::inFilter("json", CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "pma_type", [], "any", false, false, false, 55))) || CoreExtension::inFilter("text", CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "pma_type", [], "any", false, false, false, 55)))) {
                // line 56
                yield "        ";
                yield ($context["backup_field"] ?? null);
                yield "
        <textarea name=\"fields[multi_edit][";
                // line 57
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
                yield "][";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 57), "html", null, true);
                yield "]\" id=\"field_";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["id_index"] ?? null), "html", null, true);
                yield "_3\" data-type=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["data_type"] ?? null), "html", null, true);
                yield "\" dir=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["text_dir"] ?? null), "html", null, true);
                yield "\" rows=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["textarea_rows"] ?? null), "html", null, true);
                yield "\" cols=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["textarea_cols"] ?? null), "html", null, true);
                yield "\" tabindex=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($context["tab_index"] ?? null) + ($context["tab_index_for_value"] ?? null)), "html", null, true);
                yield "\"";
                // line 58
                ((($context["max_length"] ?? null)) ? (yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(((" data-maxlength=\"" . ($context["max_length"] ?? null)) . "\""), "html", null, true)) : (yield ""));
                yield ((CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "is_char", [], "any", false, false, false, 58)) ? (" class=\"charField\"") : (""));
                yield " onchange=\"return verificationsAfterFieldChange('";
                yield PhpMyAdmin\Sanitize::escapeJsString(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 58));
                yield "', '";
                yield PhpMyAdmin\Sanitize::escapeJsString(($context["row_id"] ?? null));
                yield "', '";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "pma_type", [], "any", false, false, false, 58), "html", null, true);
                yield "')\">";
                // line 60
                yield (((is_string($__internal_compile_0 = ($context["special_chars"] ?? null)) && is_string($__internal_compile_1 = "
") && str_starts_with($__internal_compile_0, $__internal_compile_1))) ? ("
") : (""));
                yield ($context["special_chars"] ?? null);
                // line 61
                yield "</textarea>
        ";
                // line 62
                if ((CoreExtension::inFilter("text", CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "pma_type", [], "any", false, false, false, 62)) && (Twig\Extension\CoreExtension::length($this->env->getCharset(), ($context["special_chars"] ?? null)) > 32000))) {
                    // line 63
                    yield "          </td>
          <td>
          ";
yield _gettext("Because of its length,<br> this column might not be editable.");
                    // line 66
                    yield "        ";
                }
                // line 67
                yield "      ";
            } elseif ((CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "pma_type", [], "any", false, false, false, 67) == "enum")) {
                // line 68
                yield "        ";
                yield ($context["backup_field"] ?? null);
                yield "
        <input type=\"hidden\" name=\"fields_type[multi_edit][";
                // line 69
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
                yield "][";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 69), "html", null, true);
                yield "]\" value=\"enum\">
        ";
                // line 70
                if ((Twig\Extension\CoreExtension::length($this->env->getCharset(), CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Type", [], "any", false, false, false, 70)) > 20)) {
                    // line 71
                    yield "          <select name=\"fields[multi_edit][";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
                    yield "][";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 71), "html", null, true);
                    yield "]\" class=\"textfield\" tabindex=\"";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($context["tab_index"] ?? null) + ($context["tab_index_for_value"] ?? null)), "html", null, true);
                    yield "\" id=\"field_";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["id_index"] ?? null), "html", null, true);
                    yield "_3\" onchange=\"return verificationsAfterFieldChange('";
                    yield PhpMyAdmin\Sanitize::escapeJsString(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 71));
                    yield "', '";
                    yield PhpMyAdmin\Sanitize::escapeJsString(($context["row_id"] ?? null));
                    yield "', '";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "pma_type", [], "any", false, false, false, 71), "html", null, true);
                    yield "')\">
            <option value=\"\"></option>
            ";
                    // line 73
                    $context['_parent'] = $context;
                    $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "values", [], "any", false, false, false, 73));
                    foreach ($context['_seq'] as $context["_key"] => $context["enum_value"]) {
                        // line 74
                        yield "              <option value=\"";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["enum_value"], "plain", [], "any", false, false, false, 74), "html", null, true);
                        yield "\"";
                        yield (((CoreExtension::getAttribute($this->env, $this->source, $context["enum_value"], "plain", [], "any", false, false, false, 74) == ($context["enum_selected_value"] ?? null))) ? (" selected") : (""));
                        yield ">";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["enum_value"], "plain", [], "any", false, false, false, 74), "html", null, true);
                        yield "</option>
            ";
                    }
                    $_parent = $context['_parent'];
                    unset($context['_seq'], $context['_iterated'], $context['_key'], $context['enum_value'], $context['_parent'], $context['loop']);
                    $context = array_intersect_key($context, $_parent) + $_parent;
                    // line 76
                    yield "          </select>
        ";
                } else {
                    // line 78
                    yield "          ";
                    $context['_parent'] = $context;
                    $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "values", [], "any", false, false, false, 78));
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
                    foreach ($context['_seq'] as $context["_key"] => $context["enum_value"]) {
                        // line 79
                        yield "            <input type=\"radio\" name=\"fields[multi_edit][";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
                        yield "][";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 79), "html", null, true);
                        yield "]\" value=\"";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["enum_value"], "plain", [], "any", false, false, false, 79), "html", null, true);
                        yield "\" class=\"textfield\" tabindex=\"";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($context["tab_index"] ?? null) + ($context["tab_index_for_value"] ?? null)), "html", null, true);
                        yield "\" id=\"field_";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["id_index"] ?? null), "html", null, true);
                        yield "_3_";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["loop"], "index0", [], "any", false, false, false, 79), "html", null, true);
                        yield "\" onchange=\"return verificationsAfterFieldChange('";
                        yield PhpMyAdmin\Sanitize::escapeJsString(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 79));
                        yield "', '";
                        yield PhpMyAdmin\Sanitize::escapeJsString(($context["row_id"] ?? null));
                        yield "', '";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "pma_type", [], "any", false, false, false, 79), "html", null, true);
                        yield "')\"";
                        yield (((CoreExtension::getAttribute($this->env, $this->source, $context["enum_value"], "plain", [], "any", false, false, false, 79) == ($context["enum_selected_value"] ?? null))) ? (" checked") : (""));
                        yield ">
            <label for=\"field_";
                        // line 80
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["id_index"] ?? null), "html", null, true);
                        yield "_3_";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["loop"], "index0", [], "any", false, false, false, 80), "html", null, true);
                        yield "\">";
                        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["enum_value"], "plain", [], "any", false, false, false, 80), "html", null, true);
                        yield "</label>
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
                    unset($context['_seq'], $context['_iterated'], $context['_key'], $context['enum_value'], $context['_parent'], $context['loop']);
                    $context = array_intersect_key($context, $_parent) + $_parent;
                    // line 82
                    yield "        ";
                }
                // line 83
                yield "      ";
            } elseif ((CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "pma_type", [], "any", false, false, false, 83) == "set")) {
                // line 84
                yield "        ";
                yield ($context["backup_field"] ?? null);
                yield "
        <input type=\"hidden\" name=\"fields_type[multi_edit][";
                // line 85
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
                yield "][";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 85), "html", null, true);
                yield "]\" value=\"set\">
        <select name=\"fields[multi_edit][";
                // line 86
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
                yield "][";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 86), "html", null, true);
                yield "][]\" class=\"textfield\" tabindex=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($context["tab_index"] ?? null) + ($context["tab_index_for_value"] ?? null)), "html", null, true);
                yield "\" size=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["set_select_size"] ?? null), "html", null, true);
                yield "\" id=\"field_";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["id_index"] ?? null), "html", null, true);
                yield "_3\" onchange=\"return verificationsAfterFieldChange('";
                yield PhpMyAdmin\Sanitize::escapeJsString(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 86));
                yield "', '";
                yield PhpMyAdmin\Sanitize::escapeJsString(($context["row_id"] ?? null));
                yield "', '";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "pma_type", [], "any", false, false, false, 86), "html", null, true);
                yield "')\" multiple>
          ";
                // line 87
                $context['_parent'] = $context;
                $context['_seq'] = CoreExtension::ensureTraversable(($context["set_values"] ?? null));
                foreach ($context['_seq'] as $context["_key"] => $context["set_value"]) {
                    // line 88
                    yield "            <option value=\"";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["set_value"], "plain", [], "any", false, false, false, 88), "html", null, true);
                    yield "\"";
                    yield ((CoreExtension::inFilter(CoreExtension::getAttribute($this->env, $this->source, $context["set_value"], "plain", [], "any", false, false, false, 88), Twig\Extension\CoreExtension::split($this->env->getCharset(), ($context["data"] ?? null), ","))) ? (" selected") : (""));
                    yield ">";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["set_value"], "plain", [], "any", false, false, false, 88), "html", null, true);
                    yield "</option>
          ";
                }
                $_parent = $context['_parent'];
                unset($context['_seq'], $context['_iterated'], $context['_key'], $context['set_value'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 90
                yield "        </select>
      ";
            } elseif ((CoreExtension::getAttribute($this->env, $this->source,             // line 91
($context["column"] ?? null), "is_binary", [], "any", false, false, false, 91) || CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "is_blob", [], "any", false, false, false, 91))) {
                // line 92
                yield "        ";
                if (($context["is_column_protected_blob"] ?? null)) {
                    // line 93
                    yield "          ";
yield _gettext("Binary - do not edit");
                    // line 94
                    yield "          (";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["blob_value"] ?? null), "html", null, true);
                    yield " ";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["blob_value_unit"] ?? null), "html", null, true);
                    yield ")
          <input type=\"hidden\" name=\"fields[multi_edit][";
                    // line 95
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
                    yield "][";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 95), "html", null, true);
                    yield "]\" value=\"\">
          <input type=\"hidden\" name=\"fields_type[multi_edit][";
                    // line 96
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
                    yield "][";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 96), "html", null, true);
                    yield "]\" value=\"protected\">
        ";
                } elseif ((CoreExtension::getAttribute($this->env, $this->source,                 // line 97
($context["column"] ?? null), "is_blob", [], "any", false, false, false, 97) || (CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "len", [], "any", false, false, false, 97) > ($context["limit_chars"] ?? null)))) {
                    // line 98
                    yield "          ";
                    yield ($context["backup_field"] ?? null);
                    yield "
          <input type=\"hidden\" name=\"fields_type[multi_edit][";
                    // line 99
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
                    yield "][";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 99), "html", null, true);
                    yield "]\" value=\"hex\">
          <textarea name=\"fields[multi_edit][";
                    // line 100
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
                    yield "][";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 100), "html", null, true);
                    yield "]\" id=\"field_";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["id_index"] ?? null), "html", null, true);
                    yield "_3\" data-type=\"HEX\" dir=\"";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["text_dir"] ?? null), "html", null, true);
                    yield "\" rows=\"";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["textarea_rows"] ?? null), "html", null, true);
                    yield "\" cols=\"";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["textarea_cols"] ?? null), "html", null, true);
                    yield "\" tabindex=\"";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($context["tab_index"] ?? null) + ($context["tab_index_for_value"] ?? null)), "html", null, true);
                    yield "\"";
                    // line 101
                    ((($context["max_length"] ?? null)) ? (yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(((" data-maxlength=\"" . ($context["max_length"] ?? null)) . "\""), "html", null, true)) : (yield ""));
                    yield ((CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "is_char", [], "any", false, false, false, 101)) ? (" class=\"charField\"") : (""));
                    yield " onchange=\"return verificationsAfterFieldChange('";
                    yield PhpMyAdmin\Sanitize::escapeJsString(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 101));
                    yield "', '";
                    yield PhpMyAdmin\Sanitize::escapeJsString(($context["row_id"] ?? null));
                    yield "', '";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "pma_type", [], "any", false, false, false, 101), "html", null, true);
                    yield "')\">";
                    // line 103
                    yield (((is_string($__internal_compile_2 = ($context["special_chars"] ?? null)) && is_string($__internal_compile_3 = "
") && str_starts_with($__internal_compile_2, $__internal_compile_3))) ? ("
") : (""));
                    yield ($context["special_chars"] ?? null);
                    // line 104
                    yield "</textarea>
        ";
                } else {
                    // line 106
                    yield "          ";
                    yield ($context["backup_field"] ?? null);
                    yield "
          <input type=\"hidden\" name=\"fields_type[multi_edit][";
                    // line 107
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
                    yield "][";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 107), "html", null, true);
                    yield "]\" value=\"hex\">
          ";
                    // line 108
                    yield ($context["input_field_html"] ?? null);
                    yield "
        ";
                }
                // line 110
                yield "        ";
                if ((($context["is_upload"] ?? null) && CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "is_blob", [], "any", false, false, false, 110))) {
                    // line 111
                    yield "          <br>
          ";
                    // line 113
                    yield "          <input type=\"file\" name=\"fields_upload[multi_edit][";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
                    yield "][";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 113), "html", null, true);
                    yield "]\" class=\"textfield noDragDrop\" id=\"field_";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["id_index"] ?? null), "html", null, true);
                    yield "_3\" size=\"10\" onchange=\"return verificationsAfterFieldChange('";
                    yield PhpMyAdmin\Sanitize::escapeJsString(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "Field_md5", [], "any", false, false, false, 113));
                    yield "', '";
                    yield PhpMyAdmin\Sanitize::escapeJsString(($context["row_id"] ?? null));
                    yield "', '";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "pma_type", [], "any", false, false, false, 113), "html", null, true);
                    yield "')\">
          ";
                    // line 114
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["max_upload_size"] ?? null), "html", null, true);
                    yield "
        ";
                }
                // line 116
                yield "        ";
                yield ($context["select_option_for_upload"] ?? null);
                yield "
      ";
            } else {
                // line 118
                yield "        ";
                yield ($context["value"] ?? null);
                yield "
      ";
            }
            // line 120
            yield "
      ";
            // line 121
            if (CoreExtension::inFilter(CoreExtension::getAttribute($this->env, $this->source, ($context["column"] ?? null), "pma_type", [], "any", false, false, false, 121), ($context["gis_data_types"] ?? null))) {
                // line 122
                yield "        <span class=\"open_gis_editor\" data-row-id=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["row_id"] ?? null), "html", null, true);
                yield "\">
          ";
                // line 123
                yield PhpMyAdmin\Html\Generator::linkOrButton("#", null, PhpMyAdmin\Html\Generator::getIcon("b_edit", _gettext("Edit/Insert")));
                yield "
        </span>
      ";
            }
            // line 126
            yield "    ";
        }
        // line 127
        yield "  </td>
</tr>
";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "table/insert/column_row.twig";
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
        return array (  637 => 127,  634 => 126,  628 => 123,  623 => 122,  621 => 121,  618 => 120,  612 => 118,  606 => 116,  601 => 114,  586 => 113,  583 => 111,  580 => 110,  575 => 108,  569 => 107,  564 => 106,  560 => 104,  555 => 103,  545 => 101,  530 => 100,  524 => 99,  519 => 98,  517 => 97,  511 => 96,  505 => 95,  498 => 94,  495 => 93,  492 => 92,  490 => 91,  487 => 90,  474 => 88,  470 => 87,  452 => 86,  446 => 85,  441 => 84,  438 => 83,  435 => 82,  415 => 80,  392 => 79,  374 => 78,  370 => 76,  357 => 74,  353 => 73,  335 => 71,  333 => 70,  327 => 69,  322 => 68,  319 => 67,  316 => 66,  311 => 63,  309 => 62,  306 => 61,  301 => 60,  291 => 58,  274 => 57,  269 => 56,  267 => 55,  262 => 53,  246 => 52,  238 => 51,  233 => 50,  231 => 49,  223 => 48,  205 => 47,  199 => 46,  194 => 45,  191 => 44,  185 => 42,  183 => 41,  177 => 39,  171 => 37,  167 => 35,  158 => 33,  150 => 32,  142 => 31,  130 => 30,  121 => 29,  119 => 28,  115 => 26,  112 => 25,  105 => 21,  91 => 20,  88 => 19,  84 => 17,  82 => 16,  77 => 15,  74 => 14,  72 => 13,  69 => 12,  63 => 9,  58 => 8,  56 => 7,  46 => 4,  42 => 3,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "table/insert/column_row.twig", "/home/oliverbob/ginto/vendor/phpmyadmin/phpmyadmin/templates/table/insert/column_row.twig");
    }
}
