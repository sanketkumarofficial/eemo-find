@if(session('status'))
<div class="alert success" role="status"><x-icon name="check" /><span>{{ session('status') }}</span></div>
@endif
@if($errors->any())
<div class="alert error" role="alert"><x-icon name="shield" /><div><strong>Please check the information below.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
@endif
