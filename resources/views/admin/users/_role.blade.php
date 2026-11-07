@php
    $roleLabels = ['customer' => 'Customer', 'admin' => 'Admin', 'worker' => 'Worker'];
@endphp

<div class="mb-3">
    <label class="form-label" for="role">
        Role
        <span class="text-danger" aria-hidden="true">*</span>
        <span class="visually-hidden">required</span>
    </label>
    <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
        @foreach ($roleLabels as $value => $label)
            <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
        @endforeach
    </select>
    @error('role')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
