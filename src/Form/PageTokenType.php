<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Form;

use Solido\QueryLanguage\Form\DataTransformer\PageTokenTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class PageTokenType extends AbstractType
{
    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addViewTransformer(new PageTokenTransformer());
    }

    public function getParent(): string|null
    {
        return TextType::class;
    }
}
