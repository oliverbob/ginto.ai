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

/* modals/preview_sql_confirmation.twig */
class __TwigTemplate_af217de4dceaa0eee24f1553150544a4 extends Template
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
        yield "<div class=\"modal fade\" id=\"previewSqlConfirmModal\" tabindex=\"-1\" aria-labelledby=\"previewSqlConfirmModalLabel\" aria-hidden=\"true\">
  <div class=\"modal-dialog\">
    <div class=\"modal-content\">
      <div class=\"modal-header\">
        <h5 class=\"modal-title\" id=\"previewSqlConfirmModalLabel\">";
yield _gettext("Loading");
        // line 5
        yield "</h5>
        <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\" aria-label=\"";
yield _gettext("Close");
        // line 6
        yield "\"></button>
      </div>
      <div class=\"modal-body preview_sql\">
        <code class=\"sql\">
          <pre id=\"previewSqlConfirmCode\"></pre>
        </code>
      </div>
      <div class=\"modal-footer\">
        <button type=\"button\" class=\"btn btn-secondary\" id=\"previewSQLConfirmOkButton\">";
yield _gettext("OK");
        // line 14
        yield "</button>
        <button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">";
yield _gettext("Close");
        // line 15
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
        return "modals/preview_sql_confirmation.twig";
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
        return array (  64 => 15,  60 => 14,  49 => 6,  45 => 5,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "modals/preview_sql_confirmation.twig", "/home/oliverbob/ginto/vendor/phpmyadmin/phpmyadmin/templates/modals/preview_sql_confirmation.twig");
    }
}
