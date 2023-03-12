<?php

/*
 * This file is part of the "Import bundle" for Kimai.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ImportBundle\Form;

use KimaiPlugin\ImportBundle\Model\TimesheetImportModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TimesheetImportForm extends AbstractType
{
    public function getParent(): string
    {
        return ImportForm::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('globalActivities', CheckboxType::class, [
                'label' => 'importer.global_activity',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TimesheetImportModel::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'import_csv_data',
            'method' => 'POST',
        ]);
    }
}
