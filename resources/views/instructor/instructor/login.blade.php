@php
    $pageTitle='Instructor Login'; $portalName='Instructor Portal'; $portalDescription='Sign in to review student clearances.'; $roleIcon='bi-person-video3';
    $submitRoute=route('instructor.login.submit'); $recoveryPortal='instructor';
    $loginName='email'; $loginType='email'; $loginLabel='Email Address'; $loginPlaceholder='Instructor email address'; $loginIcon='bi-envelope';
    $roleOptions=[]; $roleName=''; $roleLabel=''; $rolePlaceholder=''; $showRemember=false;
@endphp
@include('auth.portal-login')
