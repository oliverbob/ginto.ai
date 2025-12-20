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

/* select_all.twig */
class __TwigTemplate_c2fdd3d38217fe2f4bcd838a8f969e49 extends Template
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
        yield "<img class=\"selectallarrow\" src=\"";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->extensions['PhpMyAdmin\Twig\AssetExtension']->getImagePath((("arrow_" . ($context["text_dir"] ?? null)) . ".png")), "html", null, true);
        yield "\"
    width=\"38\" height=\"22\" alt=\"";
yield _gettext("With selected:");
        // line 2
        yield "\">
<input type=\"checkbox\" id=\"";
        // line 3
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["form_name"] ?? null), "html", null, true);
        yield "_checkall\" class=\"checkall_box\"
    title=\"";
yield _gettext("Check all");
        // line 4
        yield "\">
<label for=\"";
        // line 5
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["form_name"] ?? null), "html", null, true);
        yield "_checkall\">";
yield _gettext("Check all");
        yield "</label>
<em class=\"with-selected\">";
yield _gettext("With selected:");
        // line 6
        yield "</em>
";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "select_all.twig";
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
        return array (  62 => 6,  55 => 5,  52 => 4,  47 => 3,  44 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "select_all.twig", "/home/oliverbob/ginto/vendor/phpmyadmin/phpmyadmin/templates/select_all.twig");
    }
}
