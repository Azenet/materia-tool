<?php

namespace App\Form;

use App\Entity\Materia;
use App\Entity\MateriaLoadoutItem;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MateriaLoadoutItemType extends AbstractType {
	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder
			->add('materia', EntityType::class, [
				'required'     => false,
				'class'        => Materia::class,
				'choice_label' => 'id'
			])
			->add('pinned', CheckboxType::class, [
				'required' => false
			]);
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults([
			'data_class' => MateriaLoadoutItem::class,
		]);
	}
}
