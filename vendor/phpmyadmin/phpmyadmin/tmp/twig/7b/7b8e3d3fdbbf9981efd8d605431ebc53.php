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

/* sql/query.twig */
class __TwigTemplate_e25bce809bde3658a484ebd10c93ee5f extends Template
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
        yield "<form method=\"post\" action=\"";
        yield PhpMyAdmin\Url::getFromRoute("/import");
        yield "\" class=\"ajax lock-page\" id=\"sqlqueryform\" name=\"sqlform\"";
        // line 2
        yield ((($context["is_upload"] ?? null)) ? (" enctype=\"multipart/form-data\"") : (""));
        yield ">
  ";
        // line 3
        yield PhpMyAdmin\Url::getHiddenInputs(($context["db"] ?? null), ($context["table"] ?? null));
        yield "
  <input type=\"hidden\" name=\"is_js_confirmed\" value=\"0\">
  <input type=\"hidden\" name=\"pos\" value=\"0\">
  <input type=\"hidden\" name=\"goto\" value=\"";
        // line 6
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["goto"] ?? null), "html", null, true);
        yield "\">
  <input type=\"hidden\" name=\"message_to_show\" value=\"";
yield _gettext("Your SQL query has been executed successfully.");
        // line 7
        yield "\">
  <input type=\"hidden\" name=\"prev_sql_query\" value=\"";
        // line 8
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["query"] ?? null), "html", null, true);
        yield "\">

  ";
        // line 10
        if (((($context["display_tab"] ?? null) == "full") || (($context["display_tab"] ?? null) == "sql"))) {
            // line 11
            yield "    <a id=\"querybox\"></a>

    <div class=\"card mb-3\">
      <div class=\"card-header\">";
            // line 14
            yield ($context["legend"] ?? null);
            yield "</div>
      <div class=\"card-body\">
        <div id=\"queryfieldscontainer\">
          <div class=\"row\">
            <div class=\"col\">
              <div class=\"mb-3\">
                <textarea class=\"form-control\" tabindex=\"100\" name=\"sql_query\" id=\"sqlquery\" cols=\"";
            // line 20
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["textarea_cols"] ?? null), "html", null, true);
            yield "\" rows=\"";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["textarea_rows"] ?? null), "html", null, true);
            yield "\" data-textarea-auto-select=\"";
            yield ((($context["textarea_auto_select"] ?? null)) ? ("true") : ("false"));
            yield "\" aria-label=\"";
yield _gettext("SQL query");
            yield "\">";
            // line 21
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["query"] ?? null), "html", null, true);
            // line 22
            yield "</textarea>
              </div>
              <div id=\"querymessage\"></div>

              <div class=\"btn-toolbar\" role=\"toolbar\">
                ";
            // line 27
            if ( !Twig\Extension\CoreExtension::testEmpty(($context["columns_list"] ?? null))) {
                // line 28
                yield "                  <div class=\"btn-group me-2\" role=\"group\">
                    <input type=\"button\" value=\"SELECT *\" id=\"selectall\" class=\"btn btn-secondary button sqlbutton\">
                    <input type=\"button\" value=\"SELECT\" id=\"select\" class=\"btn btn-secondary button sqlbutton\">
                    <input type=\"button\" value=\"INSERT\" id=\"insert\" class=\"btn btn-secondary button sqlbutton\">
                    <input type=\"button\" value=\"UPDATE\" id=\"update\" class=\"btn btn-secondary button sqlbutton\">
                    <input type=\"button\" value=\"DELETE\" id=\"delete\" class=\"btn btn-secondary button sqlbutton\">
                  </div>
                ";
            }
            // line 36
            yield "
                <div class=\"btn-group me-2\" role=\"group\">
                  <input type=\"button\" value=\"";
yield _gettext("Clear");
            // line 38
            yield "\" id=\"clear\" class=\"btn btn-secondary button sqlbutton\">
                  ";
            // line 39
            if (($context["codemirror_enable"] ?? null)) {
                // line 40
                yield "                    <input type=\"button\" value=\"";
yield _gettext("Format");
                yield "\" id=\"format\" class=\"btn btn-secondary button sqlbutton\">
                  ";
            }
            // line 42
            yield "                </div>

                <input type=\"button\" value=\"";
yield _gettext("Get auto-saved query");
            // line 44
            yield "\" id=\"saved\" class=\"btn btn-secondary button sqlbutton\">
              </div>

              <div class=\"my-3\">
                <div class=\"form-check\">
                  <input class=\"form-check-input\" type=\"checkbox\" name=\"parameterized\" id=\"parameterized\">
                  <label class=\"form-check-label\" for=\"parameterized\">
                    ";
// l10n: Bind parameters in the SQL query using :parameterName format
yield _gettext("Bind parameters");
            // line 52
            yield "                    ";
            yield PhpMyAdmin\Html\MySQLDocumentation::showDocumentation("faq", "faq6-40");
            yield "
                  </label>
                </div>
              </div>
              <div class=\"mb-3\" id=\"parametersDiv\"></div>
            </div>

            ";
            // line 59
            if ( !Twig\Extension\CoreExtension::testEmpty(($context["columns_list"] ?? null))) {
                // line 60
                yield "              <div class=\"col-xl-2 col-lg-3\">
                <div class=\"mb-3\">
                  <label class=\"visually-hidden\" for=\"fieldsSelect\">";
yield _gettext("Columns");
                // line 62
                yield "</label>
                  <select class=\"form-select resize-vertical\" id=\"fieldsSelect\" name=\"dummy\" size=\"";
                // line 63
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["textarea_rows"] ?? null), "html", null, true);
                yield "\" ondblclick=\"Functions.insertValueQuery()\" multiple>
                    ";
                // line 64
                $context['_parent'] = $context;
                $context['_seq'] = CoreExtension::ensureTraversable(($context["columns_list"] ?? null));
                foreach ($context['_seq'] as $context["_key"] => $context["field"]) {
                    // line 65
                    yield "                      <option value=\"";
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(PhpMyAdmin\Util::backquote((($__internal_compile_0 = $context["field"]) && is_array($__internal_compile_0) || $__internal_compile_0 instanceof ArrayAccess ? ($__internal_compile_0["Field"] ?? null) : null)), "html", null, true);
                    yield "\"";
                    // line 66
                    (((( !(null === (($__internal_compile_1 = $context["field"]) && is_array($__internal_compile_1) || $__internal_compile_1 instanceof ArrayAccess ? ($__internal_compile_1["Field"] ?? null) : null)) &&  !(null === (($__internal_compile_2 = $context["field"]) && is_array($__internal_compile_2) || $__internal_compile_2 instanceof ArrayAccess ? ($__internal_compile_2["Comment"] ?? null) : null))) && (Twig\Extension\CoreExtension::length($this->env->getCharset(), (($__internal_compile_3 = $context["field"]) && is_array($__internal_compile_3) || $__internal_compile_3 instanceof ArrayAccess ? ($__internal_compile_3["Field"] ?? null) : null)) > 0))) ? (yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(((" title=\"" . (($__internal_compile_4 = $context["field"]) && is_array($__internal_compile_4) || $__internal_compile_4 instanceof ArrayAccess ? ($__internal_compile_4["Comment"] ?? null) : null)) . "\""), "html", null, true)) : (yield ""));
                    yield ">
                        ";
                    // line 67
                    yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape((($__internal_compile_5 = $context["field"]) && is_array($__internal_compile_5) || $__internal_compile_5 instanceof ArrayAccess ? ($__internal_compile_5["Field"] ?? null) : null), "html", null, true);
                    yield "
                      </option>
                    ";
                }
                $_parent = $context['_parent'];
                unset($context['_seq'], $context['_iterated'], $context['_key'], $context['field'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 70
                yield "                  </select>
                </div>

                <input type=\"button\" class=\"btn btn-secondary button\" id=\"insertBtn\" name=\"insert\" value=\"";
                // line 74
                if (PhpMyAdmin\Util::showIcons("ActionLinksMode")) {
                    yield "<<\" title=\"";
                }
yield _gettext("Insert");
                // line 75
                yield "\">
              </div>
            ";
            }
            // line 78
            yield "          </div>
        </div>

        ";
            // line 81
            if (($context["has_bookmark"] ?? null)) {
                // line 82
                yield "          <div class=\"row row-cols-lg-auto g-3 align-items-center\">
            <div class=\"col-6\">
              <label class=\"form-label\" for=\"bkm_label\">";
yield _gettext("Bookmark this SQL query:");
                // line 84
                yield "</label>
            </div>
            <div class=\"col-6\">
              <input class=\"form-control\" type=\"text\" name=\"bkm_label\" id=\"bkm_label\" tabindex=\"110\" value=\"\">
            </div>

            <div class=\"col-12\">
              <div class=\"form-check form-check-inline\">
                <input class=\"form-check-input\" type=\"checkbox\" name=\"bkm_all_users\" tabindex=\"111\" id=\"id_bkm_all_users\" value=\"true\">
                <label class=\"form-check-label\" for=\"id_bkm_all_users\">";
yield _gettext("Let every user access this bookmark");
                // line 93
                yield "</label>
              </div>
            </div>

            <div class=\"col-12\">
              <div class=\"form-check form-check-inline\">
                <input class=\"form-check-input\" type=\"checkbox\" name=\"bkm_replace\" tabindex=\"112\" id=\"id_bkm_replace\" value=\"true\">
                <label class=\"form-check-label\" for=\"id_bkm_replace\">";
yield _gettext("Replace existing bookmark of same name");
                // line 100
                yield "</label>
              </div>
            </div>
          </div>
        ";
            }
            // line 105
            yield "      </div>
      <div class=\"card-footer\">
        <div class=\"row row-cols-lg-auto g-3 align-items-center\">
          <div class=\"col-12\">
            <div class=\"input-group me-2\">
              <span class=\"input-group-text\">";
yield _gettext("Delimiter");
            // line 110
            yield "</span>
              <label class=\"visually-hidden\" for=\"id_sql_delimiter\">";
yield _gettext("Delimiter");
            // line 111
            yield "</label>
              <input class=\"form-control\" type=\"text\" name=\"sql_delimiter\" tabindex=\"131\" size=\"3\" value=\"";
            // line 112
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["delimiter"] ?? null), "html", null, true);
            yield "\" id=\"id_sql_delimiter\">
            </div>
          </div>

          <div class=\"col-12\">
            <div class=\"form-check form-check-inline\">
              <input class=\"form-check-input\" type=\"checkbox\" name=\"show_query\" value=\"1\" id=\"checkbox_show_query\" tabindex=\"132\">
              <label class=\"form-check-label\" for=\"checkbox_show_query\">";
yield _gettext("Show this query here again");
            // line 119
            yield "</label>
            </div>
          </div>

          <div class=\"col-12\">
            <div class=\"form-check form-check-inline\">
              <input class=\"form-check-input\" type=\"checkbox\" name=\"retain_query_box\" value=\"1\" id=\"retain_query_box\" tabindex=\"133\"";
            // line 126
            yield ((($context["retain_query_box"] ?? null)) ? (" checked") : (""));
            yield ">
              <label class=\"form-check-label\" for=\"retain_query_box\">";
yield _gettext("Retain query box");
            // line 127
            yield "</label>
            </div>
          </div>

          <div class=\"col-12\">
            <div class=\"form-check form-check-inline\">
              <input class=\"form-check-input\" type=\"checkbox\" name=\"rollback_query\" value=\"1\" id=\"rollback_query\" tabindex=\"134\">
              <label class=\"form-check-label\" for=\"rollback_query\">";
yield _gettext("Rollback when finished");
            // line 134
            yield "</label>
            </div>
          </div>

          <div class=\"col-12\">
            <div class=\"form-check\">
              <input type=\"hidden\" name=\"fk_checks\" value=\"0\">
              <input class=\"form-check-input\" type=\"checkbox\" name=\"fk_checks\" id=\"fk_checks\" value=\"1\"";
            // line 141
            yield ((($context["is_foreign_key_check"] ?? null)) ? (" checked") : (""));
            yield ">
              <label class=\"form-check-label\" for=\"fk_checks\">";
yield _gettext("Enable foreign key checks");
            // line 142
            yield "</label>
            </div>
          </div>

          <div class=\"col-12\">
            <input class=\"btn btn-primary ms-1\" type=\"submit\" id=\"button_submit_query\" name=\"SQL\" tabindex=\"200\" value=\"";
yield _gettext("Go");
            // line 147
            yield "\">
          </div>
        </div>
      </div>
    </div>
  ";
        }
        // line 153
        yield "
  ";
        // line 154
        if (((($context["display_tab"] ?? null) == "full") &&  !Twig\Extension\CoreExtension::testEmpty(($context["bookmarks"] ?? null)))) {
            // line 155
            yield "    <div class=\"card mb-3\">
      <div class=\"card-header\">";
yield _gettext("Bookmarked SQL query");
            // line 156
            yield "</div>
      <div class=\"card-body\">
        <div class=\"row row-cols-lg-auto g-3 align-items-center\">
          <div class=\"col-6\">
            <label class=\"form-label\" for=\"id_bookmark\">";
yield _gettext("Bookmark:");
            // line 160
            yield "</label>
          </div>
          <div class=\"col-6\">
            <select class=\"form-select\" name=\"id_bookmark\" id=\"id_bookmark\">
              <option value=\"\">&nbsp;</option>
              ";
            // line 165
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(($context["bookmarks"] ?? null));
            foreach ($context['_seq'] as $context["_key"] => $context["bookmark"]) {
                // line 166
                yield "                <option value=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["bookmark"], "id", [], "any", false, false, false, 166), "html", null, true);
                yield "\" data-varcount=\"";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["bookmark"], "variable_count", [], "any", false, false, false, 166), "html", null, true);
                yield "\">
                  ";
                // line 167
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(CoreExtension::getAttribute($this->env, $this->source, $context["bookmark"], "label", [], "any", false, false, false, 167), "html", null, true);
                yield "
                  ";
                // line 168
                if (CoreExtension::getAttribute($this->env, $this->source, $context["bookmark"], "is_shared", [], "any", false, false, false, 168)) {
                    // line 169
                    yield "                    (";
yield _gettext("shared");
                    yield ")
                  ";
                }
                // line 171
                yield "                </option>
              ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['bookmark'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 173
            yield "            </select>
          </div>

          <div class=\"form-check form-check-inline col-12\">
            <input class=\"form-check-input\" type=\"radio\" name=\"action_bookmark\" value=\"0\" id=\"radio_bookmark_exe\" checked>
            <label class=\"form-check-label\" for=\"radio_bookmark_exe\">";
yield _gettext("Submit");
            // line 178
            yield "</label>
          </div>
          <div class=\"form-check form-check-inline col-12\">
            <input class=\"form-check-input\" type=\"radio\" name=\"action_bookmark\" value=\"1\" id=\"radio_bookmark_view\">
            <label class=\"form-check-label\" for=\"radio_bookmark_view\">";
yield _gettext("View only");
            // line 182
            yield "</label>
          </div>
          <div class=\"form-check form-check-inline col-12\">
            <input class=\"form-check-input\" type=\"radio\" name=\"action_bookmark\" value=\"2\" id=\"radio_bookmark_del\">
            <label class=\"form-check-label\" for=\"radio_bookmark_del\">";
yield _gettext("Delete");
            // line 186
            yield "</label>
          </div>
        </div>

        <div class=\"hide\">
          ";
yield _gettext("Variables");
            // line 192
            yield "          ";
            yield PhpMyAdmin\Html\MySQLDocumentation::showDocumentation("faq", "faqbookmark");
            yield "
          <div class=\"row row-cols-auto\" id=\"bookmarkVariables\"></div>
        </div>
      </div>

      <div class=\"card-footer text-end\">
        <input class=\"btn btn-secondary\" type=\"submit\" name=\"SQL\" id=\"button_submit_bookmark\" value=\"";
yield _gettext("Go");
            // line 198
            yield "\">
      </div>
    </div>
  ";
        }
        // line 202
        yield "
  ";
        // line 203
        if (($context["can_convert_kanji"] ?? null)) {
            // line 204
            yield "    <div class=\"card mb-3\">
      <div class=\"card-body\">
        ";
            // line 206
            yield from             $this->loadTemplate("encoding/kanji_encoding_form.twig", "sql/query.twig", 206)->unwrap()->yield($context);
            // line 207
            yield "      </div>
    </div>
  ";
        }
        // line 210
        yield "</form>

<div id=\"sqlqueryresultsouter\"></div>

<div class=\"modal fade\" id=\"simulateDmlModal\" tabindex=\"-1\" aria-labelledby=\"simulateDmlModalLabel\" aria-hidden=\"true\">
  <div class=\"modal-dialog\">
    <div class=\"modal-content\">
      <div class=\"modal-header\">
        <h5 class=\"modal-title\" id=\"simulateDmlModalLabel\">";
yield _gettext("Simulate query");
        // line 218
        yield "</h5>
        <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\" aria-label=\"";
yield _gettext("Close");
        // line 219
        yield "\"></button>
      </div>
      <div class=\"modal-body\">
      </div>
      <div class=\"modal-footer\">
        <button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">";
yield _gettext("Close");
        // line 224
        yield "</button>
      </div>
    </div>
  </div>
</div>
";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "sql/query.twig";
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
        return array (  463 => 224,  455 => 219,  451 => 218,  440 => 210,  435 => 207,  433 => 206,  429 => 204,  427 => 203,  424 => 202,  418 => 198,  407 => 192,  399 => 186,  392 => 182,  385 => 178,  377 => 173,  370 => 171,  364 => 169,  362 => 168,  358 => 167,  351 => 166,  347 => 165,  340 => 160,  333 => 156,  329 => 155,  327 => 154,  324 => 153,  316 => 147,  308 => 142,  303 => 141,  294 => 134,  284 => 127,  279 => 126,  271 => 119,  260 => 112,  257 => 111,  253 => 110,  245 => 105,  238 => 100,  228 => 93,  216 => 84,  211 => 82,  209 => 81,  204 => 78,  199 => 75,  194 => 74,  189 => 70,  180 => 67,  176 => 66,  172 => 65,  168 => 64,  164 => 63,  161 => 62,  156 => 60,  154 => 59,  143 => 52,  132 => 44,  127 => 42,  121 => 40,  119 => 39,  116 => 38,  111 => 36,  101 => 28,  99 => 27,  92 => 22,  90 => 21,  81 => 20,  72 => 14,  67 => 11,  65 => 10,  60 => 8,  57 => 7,  52 => 6,  46 => 3,  42 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "sql/query.twig", "/home/oliverbob/ginto/vendor/phpmyadmin/phpmyadmin/templates/sql/query.twig");
    }
}
