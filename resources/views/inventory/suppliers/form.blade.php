@extends('layouts.app')

@section('title', $supplier->exists ? 'Edit supplier' : 'New supplier')

@php $showInlineErrors = true; @endphp

@section('content')
    <form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}">
        @csrf
        @if ($supplier->exists) @method('PUT') @endif

        <div class="page-head">
            <div>
                <div class="tiny" style="margin-bottom:5px;"><a href="{{ route('suppliers.index') }}" style="color:var(--muted);">Suppliers</a> / {{ $supplier->exists ? $supplier->name : 'New' }}</div>
                <h1 class="page-title">{{ $supplier->exists ? 'Edit supplier' : 'New supplier' }}</h1>
            </div>
            <div class="page-actions">
                <a href="{{ route('suppliers.index') }}" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">{{ $supplier->exists ? 'Save changes' : 'Create supplier' }}</button>
            </div>
        </div>

        <div class="grid-2" style="align-items:start;">
            <div class="panel">
                <div class="panel-head"><div class="panel-title">Supplier details</div></div>
                <div class="panel-body">
                    <div class="field"><label class="label">Name</label><input name="name" class="input" required maxlength="120" value="{{ old('name', $supplier->name) }}">@error('name')<div class="field-error">{{ $message }}</div>@enderror</div>
                    <div class="form-grid" style="margin-top:12px;">
                        <div class="field"><label class="label">Contact person</label><input name="contact_name" class="input" maxlength="120" value="{{ old('contact_name', $supplier->contact_name) }}"></div>
                        <div class="field"><label class="label">Phone</label><input name="phone" class="input" maxlength="40" value="{{ old('phone', $supplier->phone) }}"></div>
                    </div>
                    <div class="field" style="margin-top:12px;"><label class="label">Email</label><input name="email" type="email" class="input" maxlength="120" value="{{ old('email', $supplier->email) }}">@error('email')<div class="field-error">{{ $message }}</div>@enderror</div>
                    <div class="field" style="margin-top:12px;"><label class="label">Address</label><textarea name="address" class="textarea" rows="2" maxlength="500">{{ old('address', $supplier->address) }}</textarea></div>
                    <div class="field" style="margin-top:12px;"><label class="label">Notes</label><textarea name="notes" class="textarea" rows="2" maxlength="1000">{{ old('notes', $supplier->notes) }}</textarea></div>
                    <label class="check" style="margin-top:12px;"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier->is_active ?? true))><span>Supplier is active</span></label>
                </div>
            </div>
        </div>
    </form>
@endsection
