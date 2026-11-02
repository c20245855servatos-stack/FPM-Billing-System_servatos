<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Project</title>
    <!-- Tailwind CSS (Laravel's default) -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen font-sans">

    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md border border-gray-100">
        <h2 class="text-2xl font-semibold text-gray-900 mb-1">Create Project</h2>
        <p class="text-sm text-gray-500 mb-6">Add a new project to the billing system.</p>

        <form action="/projects" method="POST" class="space-y-5">
            @csrf <!-- Laravel Security Token -->

            <!-- Project Name Input -->
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Project Name</label>
                <input type="text" id="name" name="name" placeholder="My Awesome Project" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-black focus:border-black sm:text-sm">
                <!-- Validation Error Message -->
                @error('name')
                    <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Client Select -->
            <div>
                <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                <select id="client_id" name="client_id" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-black focus:border-black sm:text-sm">
                    <option value="">Select a client...</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}">{{ $client->company_name }}</option>
                    @endforeach
                </select>
                @error('client_id')
                    <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Submit Button -->
            <button type="submit" class="w-full bg-black text-white py-2.5 px-4 rounded-md hover:bg-gray-800 transition-colors font-medium text-sm">
                Create Project
            </button>
        </form>
    </div>

</body>
</html>