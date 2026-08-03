@php
    $pageTitle='Registrar Login'; $portalName='Registrar Portal'; $portalDescription='Sign in to manage student clearances.'; $roleIcon='bi-building-check';
    $submitRoute=route('registrar.login.submit'); $recoveryPortal='registrar';
    $loginName='login'; $loginType='text'; $loginLabel='Email or Registrar ID'; $loginPlaceholder='Email or Registrar ID'; $loginIcon='bi-person';
    $roleOptions=[]; $roleName=''; $roleLabel=''; $rolePlaceholder=''; $showRemember=true;
@endphp
@include('auth.portal-login')
