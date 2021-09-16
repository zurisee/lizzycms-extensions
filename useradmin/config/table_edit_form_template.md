// Template for editing user accounts

{{ formelem(
	type: "form-head", 
	id: 'lzy-edit-data-form-#tableCounter#',
	class: 'lzy-form lzy-edit-data-form',
	file: '~/config/users.yaml',
	ticketHash: #tickHash#,
	cancelButtonCallback: false,
	validate: true,
	labelColons: true,
	skipConfirmation: true,
	)
}}



{{ formelem(
	label: '-lzy-username',
	type: 'string',
	name: 'username',
	required: true,
    placeholder: 'unique, ascii-only'
    info: '{{ lzy-edit-user-username-info }}'
	)
}}

{{ formelem(
	label: '-lzy-groups',
	type: 'checkbox',
	options: 'guests|staff|admins',
	layout: 'h',
	name: 'groups',
	formLabel: 'Groups',
	splitChoiceElemsInDb: false,
	required: true,
    info: '{{ lzy-edit-user-groups-info }}'
	)
}}

{{ formelem(
	label: '-lzy-password',
	type: 'password',
	name: 'password',
	formLabel: 'Password',
	dataKey: password,
    info: '{{ lzy-edit-user-password-info }}'
	)
}}


{{ formelem(
	label: '-lzy-email',
	type: 'email',
	name: 'email',
	formLabel: 'E-Mail',
	dataKey: email,	
	required: true,
    info: '{{ lzy-edit-user-email-info }}'
	)
}}


{{ formelem(
	label: '-lzy-display-name',
	type: 'string',
	name: 'displayName',
    info: '{{ lzy-edit-user-displayname-info }}'
	)
}}


{{ formelem(
	label: '-lzy-access-code',
	type: 'hash',
	name: 'accessCode',
    info: '{{ lzy-edit-user-accesscode-info }}'
	)
}}


// === Extended Settings ==================================

{{ reveal(
	label: '{{ lzy-extended-settings }}',
	target: '#reveal-useradmin-extended-settings',
	class: 'lzy-reveal-controller-elem lzy-reveal-icon',
	frame: true,
	) 
}}

::: #reveal-useradmin-extended-settings

{{ formelem(
	label: '-lzy-access-code-enabled',
	type: 'toggle',
	name: 'accessCodeEnabled',
	value: true,
    info: '{{ lzy-access-code-enabled-info }}'
	)
}}

{{ formelem(
	label: '-lzy-access-code-valid-until',
	type: 'datetime',
	name: 'accessCodeValidUntil',
    info: '{{ lzy-access-code-valid-until-info }}'
	)
}}

{{ formelem(
	label: '-lzy-cal-catetory-permission',
	type: 'string',
	name: 'calCatetoryPermission',
	dataKey: calCatetoryPermission,
    placeholder: '"self" or username(s)'
	info: '{{ lzy-cal-catetory-permission-info }}',
	)
}}


{{ formelem(
	label: '-lzy-email-list',
	type: 'string',
	name: 'emaillist',
	dataKey: emaillist,
	info: '{{ lzy-email-list-info }}',
	)
}}


{{ formelem(
	label: '-lzy-inactive',
	type: 'toggle',
	name: 'inactive',
    info: '{{ lzy-edit-user-inactive-info }}'
	)
}}

:::

{{ formelem(
	'type': 'button',
	'label': '-lzy-edit-form-cancel | -lzy-edit-form-submit',
	'value': 'cancel|submit',
	)
}}


{{ formelem( type: 'form-tail' ) }}

