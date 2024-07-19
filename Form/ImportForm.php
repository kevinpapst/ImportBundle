<?php

/*
 * This file is part of the "Import bundle" for Kimai.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ImportBundle\Form;

use KimaiPlugin\ImportBundle\Importer\ImporterService;
use KimaiPlugin\ImportBundle\Model\ImportModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

class ImportForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('delimiter', ChoiceType::class, [
                'label' => 'importer.delimiter',
                'required' => true,
                'search' => false,
                'choices' => [
                    'Semicolon ;' => ';',
                    'Comma ,' => ',',
                ],
                'constraints' => [new NotBlank()]
            ])
            ->add('preview', CheckboxType::class, [
                'label' => 'preview',
                'required' => false,
            ])
            ->add('importFile', FileType::class, [
                'label' => 'importer.file_chooser',
                'help' => 'importer.file_chooser_help',
                'attr' => [
                    'accept' => 'text/csv,application/json',
                ],
                'constraints' => [
                    new NotNull(),
                    new File([
                        'maxSize' => $options['max_file_size'],
                    ])
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ImportModel::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'import_csv_data',
            'method' => 'POST',
            'max_file_size' => ImporterService::MAX_FILESIZE,
        ]);
    }
}
