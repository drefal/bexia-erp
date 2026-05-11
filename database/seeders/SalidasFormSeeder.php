<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Form;

class SalidasFormSeeder extends Seeder
{
    public function run(): void
    {
        Form::updateOrCreate(
            ['key' => 'salidas'],
            [
                'name' => 'Salidas Materiales Varios',
                'schema' => [
                    'max_items' => null,

                    'steps' => [
                        [
                            'title' => 'Datos generales',
                            'fields' => [
                                [
                                    'name' => 'fecha_salida',
                                    'label' => 'Fecha de salida',
                                    'type' => 'date',
                                    'required' => true,
                                ],
                                [
                                    'name' => 'envia_nombre',
                                    'label' => 'Nombre de quien envía',
                                    'type' => 'text',
                                    'required' => true,
                                ],
                                [
                                    'name' => 'envia_email',
                                    'label' => 'Correo electrónico de quien envía',
                                    'type' => 'email',
                                    'required' => true,
                                ],
                            ],
                        ],

                        [
                            'title' => 'Datos de envío',
                            'fields' => [
                                [
                                    'name' => 'almacen_envio',
                                    'label' => 'Almacén de envío',
                                    'type' => 'select',
                                    'required' => true,
                                    'options' => [
                                        'Calle 2',
                                        'CEDIS Tlahuac',
                                        'Otro',
                                    ],
                                ],
                            ],
                        ],

                        [
                            'title' => 'Datos de recepción',
                            'fields' => [
                                [
                                    'name' => 'almacen_recepcion',
                                    'label' => 'Almacén de recepción',
                                    'type' => 'select',
                                    'required' => true,
                                    'options' => [
                                        'Calle 2',
                                        'CEDIS Tlahuac',
                                        'Otro',
                                    ],
                                ],
                            ],
                        ],

                        [
                            'title' => 'Participantes / autorización',
                            'fields' => [
                                [
                                    'name' => 'recibe_nombre',
                                    'label' => 'Nombre de quien recibe',
                                    'type' => 'text',
                                    'required' => true,
                                ],
                                [
                                    'name' => 'recibe_email',
                                    'label' => 'Correo electrónico de quien recibe',
                                    'type' => 'email',
                                    'required' => true,
                                ],
                                [
                                    'name' => 'autoriza_nombre',
                                    'label' => 'Nombre de quien autoriza',
                                    'type' => 'text',
                                    'required' => true,
                                ],
                                [
                                    'name' => 'autoriza_email',
                                    'label' => 'Correo electrónico de quien autoriza',
                                    'type' => 'email',
                                    'required' => true,
                                ],
                                [
                                    'name' => 'proyecto',
                                    'label' => 'Proyecto',
                                    'type' => 'select',
                                    'required' => true,
                                    'options' => [
                                        'Proyecto A',
                                        'Proyecto B',
                                        'Proyecto C',
                                    ],
                                ],
                                [
                                    'name' => 'observaciones',
                                    'label' => 'Observaciones',
                                    'type' => 'textarea',
                                    'required' => true,
                                ],
                            ],
                        ],

                        [
                            'title' => 'Artículos',
                            'fields' => [
                                [
                                    'name' => 'items',
                                    'label' => 'Artículos',
                                    'type' => 'items',
                                    'required' => true,
                                    'item_fields' => [
                                        [
                                            'name' => 'cantidad',
                                            'label' => 'Cantidad',
                                            'type' => 'number',
                                            'required' => true,
                                            'min' => 0.001,
                                        ],
                                        [
                                            'name' => 'descripcion',
                                            'label' => 'Descripción',
                                            'type' => 'text',
                                            'required' => true,
                                        ],
                                        [
                                            'name' => 'foto',
                                            'label' => 'Foto del artículo',
                                            'type' => 'file',
                                            'required' => true,
                                            'disk' => 'public',
                                            'dir' => 'salidas/items',
                                        ],
                                        [
                                            'name' => 'regresa_origen',
                                            'label' => '¿Regresa al almacén origen?',
                                            'type' => 'select',
                                            'required' => true,
                                            'options' => ['Si', 'No'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );
    }
}
