<?php
$capabilities = array(
    'mod/flexible:view' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'guest' => CAP_ALLOW,
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),
    'mod/flexible:havetask' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'student' => CAP_ALLOW
        )
    ),
    'mod/flexible:grade' => array(
        'riskbitmask' => RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),
    'mod/flexible:managetasksfields'=> array(
        'captype'=>'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array (
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),
    'mod/flexible:addinstance' => array(
        'captype'=>'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array (
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),
    'mod/flexible:managetasks'=> array(
        'captype'=>'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array (
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),
    'mod/flexible:managecriterions'=> array(
        'captype'=>'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array (
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),
    'mod/flexible:viewownsubmission'=> array(
        'captype'=>'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array (
            'student' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),
    'mod/flexible:finalgrades'=> array(
        'riskbitmask' => RISK_XSS,
        'captype'=>'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array (
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),
    'mod/flexible:seeotherstasks'=> array(
        'captype'=>'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array (
            'student' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),
    'mod/flexible:seecriteriondescription'=> array(
        'captype'=>'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array (
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),
    'mod/flexible:seefielddescription'=> array(
        'captype'=>'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array (
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),
    'mod/flexible:manageanything'=> array(
        'captype'=>'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array (
            'manager' => CAP_ALLOW
        )
    )
);

