@php
    $pageTitle='Treasurer Login'; $portalName='Treasurer Portal'; $portalDescription='Sign in to manage financial clearances.'; $roleIcon='bi-wallet2';
    $submitRoute=route('treasurer.login.submit'); $recoveryPortal='treasurer';
    $loginName='login'; $loginType='text'; $loginLabel='Email or Treasurer ID'; $loginPlaceholder='Email or Treasurer ID'; $loginIcon='bi-person';
    $roleName='treasurer_type'; $roleLabel='Treasurer Role'; $rolePlaceholder='Select treasurer role'; $roleOptions=['department'=>'Department Treasurer','section'=>'Section Treasurer']; $showRemember=true;
@endphp
@include('auth.portal-login')
