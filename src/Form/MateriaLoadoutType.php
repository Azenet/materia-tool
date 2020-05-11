<?php

namespace App\Form;

use App\Entity\MateriaLoadout;
use App\Validator\PartyOrder;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;

class MateriaLoadoutType extends AbstractType {
	private TokenStorageInterface $tsi;

	public function __construct(TokenStorageInterface $tsi) {
		$this->tsi = $tsi;
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$partyOrder = $builder->create('partyOrder', TextType::class, [
			'required'    => true,
			'constraints' => [
				new NotBlank(),
				new Count(4),
				new PartyOrder()
			],
			'help'        => 'Same as above, drag/drop support coming later. This is the party order that you want when this specific loadout is going to be applied. Use comma-separated values (c,b,t,a for Cloud/Barret/Tifa/Aerith for example).'
		]);

		$partyOrder->addModelTransformer(new CallbackTransformer(static function ($value) {
			if (empty($value)) {
				return '';
			}

			return implode(',', $value);
		}, static function ($value) {
			if (empty($value)) {
				return [];
			}

			return array_map('trim', explode(',', $value));
		}));

		$builder
			->add('name')
			->add('parent', EntityType::class, [
				'required'      => false,
				'help'          => 'Drag/drop movement for this is coming later.',
				'class'         => MateriaLoadout::class,
				'choice_label'  => 'name',
				'placeholder'   => '-- No parent --',
				'query_builder' => function (EntityRepository $er) {
					return $er
						->createQueryBuilder('ml')
						->where('ml.owner = :user')
						->setParameter('user', $this->tsi->getToken()->getUser()->getId());
				}
			])
			->add($partyOrder);
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults([
			'data_class' => MateriaLoadout::class,
		]);
	}
}
