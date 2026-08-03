# Component Organization Guide

## Architecture Overview

Components are organized by portal/page to provide a clean separation of concerns and avoid naming conflicts when maintaining multiple portals.

## Directory Structure

```
resources/views/
├── components/                      # Shared components (used across portals)
│   └── [future shared components]
├── mainAdmin/
│   ├── components/                  # MainAdmin-specific components
│   │   ├── input-label.blade.php
│   │   ├── input-error.blade.php
│   │   ├── text-input.blade.php
│   │   ├── primary-button.blade.php
│   │   ├── secondary-button.blade.php
│   │   ├── danger-button.blade.php
│   │   ├── modal.blade.php
│   │   ├── dropdown.blade.php
│   │   └── ... (other admin-specific components)
│   ├── layouts/
│   ├── auth/
│   ├── profile/
│   └── ... (admin pages/features)
├── instructor/
│   ├── components/                  # Instructor-specific components
│   │   ├── esignature-widget.blade.php
│   │   └── ... (instructor-specific components)
│   ├── layouts/
│   ├── instructor/
│   └── ... (instructor pages/features)
└── profile/
    └── partials/                    # Shared profile partials
```

## Usage Examples

### MainAdmin Portal Components

In any MainAdmin view, components are referenced directly (Blade auto-discovers them):

```blade
<!-- Using component from mainAdmin/components/ -->
<x-input-label for="email" :value="__('Email')" />
<x-primary-button>{{ __('Save') }}</x-primary-button>
<x-danger-button>{{ __('Delete') }}</x-danger-button>
```

### Instructor Portal Components

In Instructor views, use components from the instructor folder:

```blade
<!-- Using component from instructor/components/ -->
<x-esignature-widget />
```

### From Different Portal Folders

If you need to use a component from another portal's folder, specify the full path:

```blade
<!-- Explicitly reference mainAdmin component from another context -->
@include('mainAdmin.components.input-label', ['value' => 'Name'])
```

## Adding New Components

### For MainAdmin Portal
1. Create component file: `resources/views/mainAdmin/components/my-component.blade.php`
2. Use in views: `<x-my-component prop="value" />`

### For Instructor Portal
1. Create component file: `resources/views/instructor/components/my-component.blade.php`
2. Use in views: `<x-my-component prop="value" />`

### For Shared Use (future)
1. Create component file: `resources/views/components/my-component.blade.php`
2. Use in any view: `<x-my-component prop="value" />`

## How It Works

Laravel's Blade template engine **automatically discovers** components in:
- `resources/views/components/` (root level)
- Any folder structure you create (e.g., `mainAdmin/components/`, `instructor/components/`)

When you use `<x-component-name>`, Blade searches for:
1. `resources/views/components/component-name.blade.php`
2. `resources/views/componentname/component-name.blade.php` (nested folders)
3. And so on...

The component discovery is configured by Laravel and works out of the box.

## Benefits

✅ **Clear Organization** - Each portal has its own components folder  
✅ **No Naming Conflicts** - MainAdmin and Instructor can each have an `input-label` component  
✅ **Scalability** - Easy to add new portals (just create a new folder)  
✅ **Maintainability** - Developers know exactly where portal-specific components live  
✅ **Separation of Concerns** - Portal-specific UI logic stays in that portal's folder  

## Best Practices

1. **Portal-Specific Components**: Keep them in the portal's `components/` folder
2. **Shared Components**: If multiple portals need the same component, put it in `resources/views/components/`
3. **Component Naming**: Use descriptive names that indicate their purpose (e.g., `input-label`, `primary-button`)
4. **Documentation**: Add PHPDoc comments to component props for clarity

## Adding a New Portal

To add a new portal (e.g., Student Portal):

1. Create folder structure:
   ```
   resources/views/student/
   ├── components/
   ├── layouts/
   └── [pages...]
   ```

2. Create components in `resources/views/student/components/`

3. Use them in views: `<x-component-name />`

That's it! No additional configuration needed.

