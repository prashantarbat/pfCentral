<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Validation\StrictRules\CreditCardRules;
use CodeIgniter\Validation\StrictRules\FileRules;
use CodeIgniter\Validation\StrictRules\FormatRules;
use CodeIgniter\Validation\StrictRules\Rules;

class Validation extends BaseConfig
{
    // --------------------------------------------------------------------
    // Setup
    // --------------------------------------------------------------------

    /**
     * Stores the classes that contain the
     * rules that are available.
     *
     * @var string[]
     */
    public array $ruleSets = [
        Rules::class,
        FormatRules::class,
        FileRules::class,
        CreditCardRules::class,
    ];

    /**
     * Specifies the views that are used to display the
     * errors.
     *
     * @var array<string, string>
     */
    public array $templates = [
        'list'   => 'CodeIgniter\Validation\Views\list',
        'single' => 'CodeIgniter\Validation\Views\single',
    ];

    // --------------------------------------------------------------------
    public $registration = [
        'first_name' => [
            'label' => 'First Name',
            'rules' => [
                'required',
                'min_length[2]',
                'max_length[254]',
            ],
        ],
        'middle_name' => [
            'label' => 'Middle Name',
            'rules' => [],
        ],
        'last_name' => [
            'label' => 'Last Name',
            'rules' => [
                'required',
                'min_length[2]',
                'max_length[254]',
            ],
        ],
        'email' => [
            'label' => 'Auth.email',
            'rules' => [
                'required',
                'max_length[254]',
                'valid_email',
                'is_unique[auth_identities.secret]',
            ],
        ],
        'mobile_number' => [
            'label' => 'Mobile Number',
            'rules' => [
                'permit_empty',
                'max_length[10]',
                'min_length[10]',
                'regex_match[/\A[0-9]+\z/]',
                'is_unique[users.mobile_number]',
            ],
        ],
        'password' => [
            'label' => 'Auth.password',
            'rules' => 'required|strong_password',
        ],
        'password_confirm' => [
            'label' => 'Auth.passwordConfirm',
            'rules' => 'required|matches[password]',
        ],        
    ];
    // --------------------------------------------------------------------
}
