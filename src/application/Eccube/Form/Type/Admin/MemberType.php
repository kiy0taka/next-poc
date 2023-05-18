<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eccube\Form\Type\Admin;

use Eccube\Common\EccubeConfig;
use Eccube\Entity\Master\Authority;
use Eccube\Entity\Master\Work;
use Eccube\Entity\Member;
use Eccube\Form\FormBuilder;
use Eccube\Form\FormError;
use Eccube\Form\FormEvent;
use Eccube\Form\Type\AbstractType;
use Eccube\Form\Type\RepeatedPasswordType;
use Eccube\Form\Type\ToggleSwitchType;
use Eccube\OptionsResolver\OptionsResolver;
use Eccube\Repository\MemberRepository;
use Eccube\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class MemberType extends AbstractType
{
    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var MemberRepository
     */
    protected $memberRepository;

    /**
     * MemberType constructor.
     *
     * @param EccubeConfig $eccubeConfig
     * @param MemberRepository $memberRepository
     */
    public function __construct(
        EccubeConfig $eccubeConfig,
        MemberRepository $memberRepository
    ) {
        $this->eccubeConfig = $eccubeConfig;
        $this->memberRepository = $memberRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => $this->eccubeConfig['eccube_stext_len']]),
                ],
            ])
            ->add('department', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => $this->eccubeConfig['eccube_stext_len']]),
                ],
            ])
            ->add('plain_password', RepeatedPasswordType::class, [
                'options' => [
                    'constraints' => [
                        new Assert\Length([
                            'min' => $this->eccubeConfig['eccube_password_min_len'],
                            'max' => $this->eccubeConfig['eccube_password_max_len'],
                        ]),
                        new Assert\Regex([
                            'pattern' => $this->eccubeConfig['eccube_password_pattern'],
                            'message' => 'form_error.password_pattern_invalid',
                        ]),
                        new Assert\NotBlank()
                    ],
                ]
            ])
            ->add('Authority', EntityType::class, [
                'class' => 'Eccube\Entity\Master\Authority',
                'expanded' => false,
                'multiple' => false,
                'placeholder' => 'admin.common.select',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
            ])
            ->add('Work', EntityType::class, [
                'class' => 'Eccube\Entity\Master\Work',
                'expanded' => true,
                'multiple' => false,
                'constraints' => [
                    new Assert\NotBlank(),
                ],
            ])
            ->add('two_factor_auth_enabled', ToggleSwitchType::class, [
            ]);

        // login idの入力は新規登録時のみとし、編集時はdisabledにする
        $builder->onPreSetData(function (FormEvent $event) {
            $form = $event->getForm();
            $data = $event->getData();

            $options = [
                'constraints' => [
                    new Assert\Length([
                        'min' => $this->eccubeConfig['eccube_id_min_len'],
                        'max' => $this->eccubeConfig['eccube_id_max_len'],
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^[[:graph:][:space:]]+$/i',
                        'message' => 'form_error.graph_only',
                    ]),
                ],
            ];

            if ($data->getId() === null) {
                $options['constraints'][] = new Assert\NotBlank();
            } else {
                $options['required'] = false;
                $options['mapped'] = false;
                $options['attr'] = [
                    'disabled' => 'disabled',
                ];
                $options['empty_data'] = $data->getLoginId();
                $options['data'] = $data->getLoginId();
            }

            $form->add('login_id', TextType::class, $options);
        });

        $builder->onPostSubmit(function (FormEvent $event) {
            /** @var Member $Member */
            $Member = $event->getData();

            // 編集時に, 非稼働で更新した場合にチェック.
            if ($Member->getId() && $Member->getWork()->getId() == Work::NON_ACTIVE) {
                // 自身を除いた稼働メンバーの件数
                $count = $this->memberRepository
                    ->createQueryBuilder('m')
                    ->select('COUNT(m)')
                    ->where('m.Work = :Work AND m.Authority = :Authority AND m.id <> :Member')
                    ->setParameter('Work', Work::ACTIVE)
                    ->setParameter('Authority', Authority::ADMIN)
                    ->setParameter('Member', $Member)
                    ->getQuery()
                    ->getSingleScalarResult();

                if ($count < 1) {
                    $form = $event->getForm();
                    $form['Work']->addError(new FormError(trans('admin.setting.system.member.work_can_not_change')));
                }
            }

            // login_idの重複をチェック。編集時はlogin_id変更不可のため、新規登録時のみチェックする。
            if ($Member->getId() === null) {
                $exist = $this->memberRepository->findOneBy(['login_id' => $Member->getLoginId()]);
                if ($exist) {
                    $form = $event->getForm();
                    $form['login_id']->addError(new FormError(trans('form_error.member_already_exists', [], 'validators')));
                }
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'Eccube\Entity\Member',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'admin_member';
    }
}
