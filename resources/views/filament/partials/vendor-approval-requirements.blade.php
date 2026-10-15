<ul class="list-disc space-y-2 ps-5 text-sm text-gray-700 dark:text-gray-200">
    @foreach ($errors as $error)
        <li>{{ $error }}</li>
    @endforeach
</ul>
