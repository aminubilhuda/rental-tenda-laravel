<form method="GET" class="inline-search">
    <input name="q" value="{{ $query }}" placeholder="Cari deskripsi...">
    <select name="user_id"><option value="">Semua pengguna</option>@foreach($activityUsers as $activityUser)<option value="{{ $activityUser->id }}" @selected(request('user_id')==$activityUser->id)>{{ $activityUser->name }}</option>@endforeach</select>
    <select name="module"><option value="">Semua modul</option>@foreach(['auth','items','categories','customers','rentals','damages','settings','users'] as $module)<option value="{{ $module }}" @selected(request('module')===$module)>{{ ucfirst($module) }}</option>@endforeach</select>
    <input type="date" name="from" value="{{ request('from') }}"><input type="date" name="to" value="{{ request('to') }}">
    <button class="button button-soft">Filter</button>
</form>
