<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserPreferencesType extends AbstractType {
	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder
			->add('materiaCoordinatesPreference', ChoiceType::class, [
				'choices'     => [
					'Row letter then number (A1, B2, ...)'                           => User::MATERIA_COORDINATES_PREFERENCE_ROW_NUMBER,
					'Letter (1, B, ...)'                                             => User::MATERIA_COORDINATES_PREFERENCE_LETTER,
					'Character, row letter then number (CA1, TB2, ...)'              => User::MATERIA_COORDINATES_PREFERENCE_CHARACTER_ROW_NUMBER,
					'Character then letter (C1, TB, ...)'                            => User::MATERIA_COORDINATES_PREFERENCE_CHARACTER_LETTER,
					'That Way MV Uses (first row 1,2,3,4,A,B,C, second row 5,6,7,8)' => User::MATERIA_COORDINATES_PREFERENCE_MV
				],
				'required'    => true,
				'constraints' => [
					new NotBlank()
				],
				'expanded' => true
			]);
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults([
			'data_class' => User::class,
		]);
	}
}
