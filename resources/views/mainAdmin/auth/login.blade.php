@php
    $pageTitle='Main Admin Login'; $portalName='Main Admin Portal'; $portalDescription='Sign in to manage the clearance system.'; $roleIcon='bi-shield-lock';
    $submitRoute=route('login.post'); $recoveryPortal='main-admin';
    $loginName='email'; $loginType='email'; $loginLabel='Email Address'; $loginPlaceholder='Admin email address'; $loginIcon='bi-envelope';
    $roleOptions=[]; $roleName=''; $roleLabel=''; $rolePlaceholder=''; $showRemember=false;
@endphp
@include('auth.portal-login')
