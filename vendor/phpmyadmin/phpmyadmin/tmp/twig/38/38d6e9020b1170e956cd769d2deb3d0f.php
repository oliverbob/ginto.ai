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

/* modals/index_dialog_modal.twig */
class __TwigTemplate_5d8a2b2875ebcc4334ceed417ebafcf4 extends Template
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
        yield "<div class=\"modal fade\" id=\"indexDialogModal\" tabindex=\"-1\" aria-labelledby=\"indexDialogModalLabel\" aria-hidden=\"true\">
  <div class=\"modal-dialog\">
    <div class=\"modal-content\">
      <div class=\"modal-header\">
        <h5 class=\"modal-title\" id=\"indexDialogModalLabel\">";
yield _gettext("Loading");
        // line 5
        yield "</h5>
        <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\" aria-label=\"";
yield _gettext("Close");
        // line 6
        yield "\"></button>
      </div>
      <div class=\"modal-body\"></div>
      <div class=\"modal-footer\">
        <button type=\"button\" class=\"btn btn-primary\" id=\"indexDialogModalGoButton\">";
yield _gettext("Go");
        // line 10
        yield "</button>
        <button type=\"button\" class=\"btn btn-secondary\" data-bs-target=\"#indexDialogPreviewModal\" data-bs-toggle=\"modal\">";
yield _gettext("Preview SQL");
        // line 11
        yield "</button>
        <button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">";
yield _gettext("Close");
        // line 12
        yield "</button>
      </div>
    </div>
  </div>
</div>
<div class=\"modal fade\" id=\"indexDialogPreviewModal\" aria-hidden=\"true\" aria-labelledby=\"indexDialogPreviewModalLabel\" tabindex=\"-1\">
  <div class=\"modal-dialog\">
    <div class=\"modal-content\">
      <div class=\"modal-header\">
        <h5 class=\"modal-title\" id=\"indexDialogPreviewModalLabel\">";
yield _gettext("Preview SQL");
        // line 21
        yield "</h5>
        <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\" aria-label=\"";
yield _gettext("Close");
        // line 22
        yield "\"></button>
      </div>
      <div class=\"modal-body\">
        <div class=\"spinner-border\" role=\"status\">
          <span class=\"visually-hidden\">";
yield _gettext("Loading…");
        // line 26
        yield "</span>
        </div>
      </div>
      <div class=\"modal-footer\">
        <button class=\"btn btn-primary\" data-bs-target=\"#indexDialogModal\" data-bs-toggle=\"modal\">";
yield _gettext("Go back");
        // line 30
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
        return "modals/index_dialog_modal.twig";
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
        return array (  94 => 30,  87 => 26,  80 => 22,  76 => 21,  64 => 12,  60 => 11,  56 => 10,  49 => 6,  45 => 5,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "modals/index_dialog_modal.twig", "/home/oliverbob/ginto/vendor/phpmyadmin/phpmyadmin/templates/modals/index_dialog_modal.twig");
    }
}
