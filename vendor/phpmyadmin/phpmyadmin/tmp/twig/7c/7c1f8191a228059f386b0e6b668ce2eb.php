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

/* display/results/page_selector.twig */
class __TwigTemplate_84d8a01530807e2441edd9169adc4fa9 extends Template
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
        yield "<td>
  <form action=\"";
        // line 2
        yield PhpMyAdmin\Url::getFromRoute("/sql");
        yield "\" method=\"post\">
    ";
        // line 3
        yield PhpMyAdmin\Url::getHiddenInputs(($context["url_params"] ?? null));
        yield "
    ";
        // line 4
        yield ($context["page_selector"] ?? null);
        yield "
  </form>
</td>
";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "display/results/page_selector.twig";
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
        return array (  49 => 4,  45 => 3,  41 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "display/results/page_selector.twig", "/home/oliverbob/ginto/vendor/phpmyadmin/phpmyadmin/templates/display/results/page_selector.twig");
    }
}
