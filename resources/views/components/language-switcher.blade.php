<form method="POST" action="{{ route('locale.update') }}" class="inline">
@csrf
@php $loc = app()->getLocale(); @endphp
<select name="lang" onchange="this.form.submit()" class="border rounded px-2 py-1">
<option value="es" @selected($loc==='es')>ES</option>
<option value="en" @selected($loc==='en')>EN</option>
<option value="pt" @selected($loc==='pt')>PT</option>
</select>
</form>