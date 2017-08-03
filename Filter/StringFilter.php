<?php

/*
 * This file is part of the PablodipAdminModuleBundle package.
 *
 * (c) Pablo Díez <pablodip@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pablodip\AdminModuleBundle\Filter;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilder;
use Molino\QueryInterface;

/**
 * StringFilter.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 */
class StringFilter extends BaseFilter
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilder $formBuilder)
    {
        $formBuilder->add('type', ChoiceType::class, array(
            'choices' => array(
                'Contains' => 'contains',
                'Not contains' => 'not_contains',
                'Exactly' => 'exactly',
            ),
            'choices_as_values' => true,
            'choice_value' => function ($choice) {
                return $choice;
            },
        ));
        $formBuilder->add('value', TextType::class, array('required' => false));
    }

    /**
     * {@inheritdoc}
     */
    public function getConstraints()
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function filter(QueryInterface $query, $fieldName, array $data)
    {
        // no filter
        if (!$data['value']) {
            return;
        }

        if ('contains' === $data['type']) {
            $query->filterLike($fieldName, sprintf('*%s*', $data['value']));
        } elseif ('not_contains' === $data['type']) {
            $query->filterNotLike($fieldName, sprintf('*%s*', $data['value']));
        } elseif ('exactly' === $data['type']) {
            $query->filterEqual($fieldName, $data['value']);
        }
    }
}
