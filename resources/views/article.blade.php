@extends('layouts.app')

@section('content')
    <div class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h1 class="text-3xl font-bold leading-tight text-gray-900">
                    {{ $article->title ?? $article->keyword }}
                </h1>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">
                    Published on {{ $article->created_at->format('F j, Y') }}
                </p>
                <div class="mt-2">
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $article->status === 'completed' || $article->status === 'published' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                        {{ ucfirst($article->status) }}
                    </span>
                </div>
            </div>
            <div class="px-4 py-5 sm:p-6 prose max-w-none text-gray-700">
                {!! $article->html_content !!}
            </div>
            <div class="px-4 py-4 sm:px-6 bg-gray-50 border-t border-gray-200 flex justify-between items-center">
                <a href="{{ route('projects.show', $article->project_id) }}"
                    class="text-indigo-600 hover:text-indigo-900 font-medium">
                    &larr; Volver al Proyecto
                </a>
                <a href="{{ route('projects.index') }}"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    Ir al Panel Principal
                </a>
            </div>
        </div>
    </div>
@endsection