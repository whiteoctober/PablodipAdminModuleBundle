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
use Symfony\Component\Form\FormBuilder;
use Molino\QueryInterface;

/**
 * BooleanFilter.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 */
class BooleanFilter extends BaseFilter
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilder $formBuilder)
    {
        $formBuilder->add('value', ChoiceType::class, array(
            'choices' => array(
                'Yes or No' => 'yes_or_no',
                'Yes' => 'yes',
                'No' => 'no',
            ),
            'choices_as_values' => true,
        ));
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
        if ('yes_or_no' == $data['value']) {
            return;
        }
        if ('yes' == $data['value']) {
            $query->filterEqual($fieldName, true);
        } elseif ('no' == $data['value']) {
            $query->filterEqual($fieldName, false);
        }
    }
}
