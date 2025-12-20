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

/* table/insert/actions_panel.twig */
class __TwigTemplate_852afb2d44c79ca3e6bafce652a07442 extends Template
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
        yield "<fieldset class=\"pma-fieldset\" id=\"actions_panel\">
  <table class=\"table table-borderless w-auto tdblock\">
    <tr>
      <td class=\"text-nowrap align-middle\">
        <select name=\"submit_type\" class=\"control_at_footer\">
          ";
        // line 6
        if ( !Twig\Extension\CoreExtension::testEmpty(($context["where_clause"] ?? null))) {
            // line 7
            yield "            <option value=\"save\">";
yield _gettext("Save");
            yield "</option>
          ";
        }
        // line 9
        yield "          <option value=\"insert\">";
yield _gettext("Insert as new row");
        yield "</option>
          <option value=\"insertignore\">";
yield _gettext("Insert as new row and ignore errors");
        // line 10
        yield "</option>
          <option value=\"showinsert\">";
yield _gettext("Show insert query");
        // line 11
        yield "</option>
        </select>
      </td>
      <td class=\"align-middle\">
        <strong>";
yield _gettext("and then");
        // line 15
        yield "</strong>
      </td>
      <td class=\"text-nowrap align-middle\">
        <select name=\"after_insert\" class=\"control_at_footer\">
          <option value=\"back\"";
        // line 19
        yield (((($context["after_insert"] ?? null) == "back")) ? (" selected") : (""));
        yield ">";
yield _gettext("Go back to previous page");
        yield "</option>
          <option value=\"new_insert\"";
        // line 20
        yield (((($context["after_insert"] ?? null) == "new_insert")) ? (" selected") : (""));
        yield ">";
yield _gettext("Insert another new row");
        yield "</option>
          ";
        // line 21
        if ( !Twig\Extension\CoreExtension::testEmpty(($context["where_clause"] ?? null))) {
            // line 22
            yield "            <option value=\"same_insert\"";
            yield (((($context["after_insert"] ?? null) == "same_insert")) ? (" selected") : (""));
            yield ">";
yield _gettext("Go back to this page");
            yield "</option>
            ";
            // line 23
            if ((($context["found_unique_key"] ?? null) && ($context["is_numeric"] ?? null))) {
                // line 24
                yield "              <option value=\"edit_next\"";
                yield (((($context["after_insert"] ?? null) == "edit_next")) ? (" selected") : (""));
                yield ">";
yield _gettext("Edit next row");
                yield "</option>
            ";
            }
            // line 26
            yield "          ";
        }
        // line 27
        yield "        </select>
      </td>
    </tr>
    <tr>
      <td>
        ";
        // line 32
        yield PhpMyAdmin\Html\Generator::showHint(_gettext("Use TAB key to move from value to value, or CTRL+arrows to move anywhere."));
        yield "
      </td>
      <td colspan=\"3\" class=\"text-end align-middle\">
        <input type=\"button\" class=\"btn btn-secondary preview_sql control_at_footer\" value=\"";
yield _gettext("Preview SQL");
        // line 35
        yield "\">
        <input type=\"reset\" class=\"btn btn-secondary control_at_footer\" value=\"";
yield _gettext("Reset");
        // line 36
        yield "\">
        <input type=\"submit\" class=\"btn btn-primary control_at_footer\" value=\"";
yield _gettext("Go");
        // line 37
        yield "\" id=\"buttonYes\">
      </td>
    </tr>
  </table>
</fieldset>
<div class=\"modal fade\" id=\"previewSqlModal\" tabindex=\"-1\" aria-labelledby=\"previewSqlModalLabel\" aria-hidden=\"true\">
  <div class=\"modal-dialog\">
    <div class=\"modal-content\">
      <div class=\"modal-header\">
        <h5 class=\"modal-title\" id=\"previewSqlModalLabel\">";
yield _gettext("Loading");
        // line 46
        yield "</h5>
        <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\" aria-label=\"";
yield _gettext("Close");
        // line 47
        yield "\"></button>
      </div>
      <div class=\"modal-body\">
      </div>
      <div class=\"modal-footer\">
        <button type=\"button\" class=\"btn btn-secondary\" id=\"previewSQLCloseButton\" data-bs-dismiss=\"modal\">";
yield _gettext("Close");
        // line 52
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
        return "table/insert/actions_panel.twig";
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
        return array (  156 => 52,  148 => 47,  144 => 46,  132 => 37,  128 => 36,  124 => 35,  117 => 32,  110 => 27,  107 => 26,  99 => 24,  97 => 23,  90 => 22,  88 => 21,  82 => 20,  76 => 19,  70 => 15,  63 => 11,  59 => 10,  53 => 9,  47 => 7,  45 => 6,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "table/insert/actions_panel.twig", "/home/oliverbob/ginto/vendor/phpmyadmin/phpmyadmin/templates/table/insert/actions_panel.twig");
    }
}
