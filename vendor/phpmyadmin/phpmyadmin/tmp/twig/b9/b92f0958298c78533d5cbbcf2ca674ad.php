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

/* table/insert/get_head_and_foot_of_insert_row_table.twig */
class __TwigTemplate_62bd74292936eaea3068fe4d7f1a92a2 extends Template
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
        yield "<div class=\"table-responsive-lg\">
  <table class=\"table table-striped align-middle my-3 insertRowTable w-auto\">
    <thead>
    <tr>
      <th>";
yield _gettext("Column");
        // line 5
        yield "</th>
      ";
        // line 6
        yield ($context["type"] ?? null);
        yield "
      ";
        // line 7
        yield ($context["function"] ?? null);
        yield "
      <th>";
yield _gettext("Null");
        // line 8
        yield "</th>
      <th class=\"w-50\">";
yield _gettext("Value");
        // line 9
        yield "</th>
    </tr>
    </thead>
     <tfoot>
    <tr>
      <th colspan=\"5\" class=\"tblFooters text-end\">
        <input class=\"btn btn-primary\" type=\"submit\" value=\"";
yield _gettext("Go");
        // line 15
        yield "\">
        </th>
      </tr>
    </tfoot>";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "table/insert/get_head_and_foot_of_insert_row_table.twig";
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
        return array (  70 => 15,  61 => 9,  57 => 8,  52 => 7,  48 => 6,  45 => 5,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "table/insert/get_head_and_foot_of_insert_row_table.twig", "/home/oliverbob/ginto/vendor/phpmyadmin/phpmyadmin/templates/table/insert/get_head_and_foot_of_insert_row_table.twig");
    }
}
