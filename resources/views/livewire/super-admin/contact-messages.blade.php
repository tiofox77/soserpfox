<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-envelope mr-2"></i>Mensagens de Contato</h4>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" wire:model.live="search" class="form-control" placeholder="Buscar por nome, email ou empresa...">
                        </div>
                        <div class="col-md-3">
                            <select wire:model.live="statusFilter" class="form-control">
                                <option value="">Todos os Status</option>
                                <option value="new">Novas</option>
                                <option value="read">Lidas</option>
                                <option value="replied">Respondidas</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>Telefone</th>
                                    <th>Empresa</th>
                                    <th>Status</th>
                                    <th>Data</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($messages as $message)
                                    <tr>
                                        <td>
                                            <strong>{{ $message->name }}</strong>
                                            @if($message->status === 'new')
                                                <span class="badge badge-danger badge-sm ml-2">Nova</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="mailto:{{ $message->email }}">{{ $message->email }}</a>
                                        </td>
                                        <td>
                                            @if($message->phone)
                                                <a href="tel:{{ $message->phone }}">{{ $message->phone }}</a>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>{{ $message->company ?? '-' }}</td>
                                        <td>
                                            @if($message->status === 'new')
                                                <span class="badge badge-danger">Nova</span>
                                            @elseif($message->status === 'read')
                                                <span class="badge badge-warning">Lida</span>
                                            @else
                                                <span class="badge badge-success">Respondida</span>
                                            @endif
                                        </td>
                                        <td>{{ $message->created_at->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#messageModal{{ $message->id }}">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            @if($message->status === 'new')
                                                <button wire:click="markAsRead({{ $message->id }})" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            @endif
                                            
                                            @if($message->status !== 'replied')
                                                <button wire:click="markAsReplied({{ $message->id }})" class="btn btn-sm btn-success">
                                                    <i class="fas fa-reply"></i>
                                                </button>
                                            @endif
                                            
                                            <button wire:click="delete({{ $message->id }})" wire:confirm="Tem certeza que deseja excluir esta mensagem?" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <!-- Modal -->
                                    <div class="modal fade" id="messageModal{{ $message->id }}" tabindex="-1" role="dialog">
                                        <div class="modal-dialog modal-lg" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title">Mensagem de {{ $message->name }}</h5>
                                                    <button type="button" class="close text-white" data-dismiss="modal">
                                                        <span>&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <strong>Nome:</strong><br>
                                                            {{ $message->name }}
                                                        </div>
                                                        <div class="col-md-6">
                                                            <strong>Email:</strong><br>
                                                            <a href="mailto:{{ $message->email }}">{{ $message->email }}</a>
                                                        </div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <strong>Telefone:</strong><br>
                                                            {{ $message->phone ?? 'Não informado' }}
                                                        </div>
                                                        <div class="col-md-6">
                                                            <strong>Empresa:</strong><br>
                                                            {{ $message->company ?? 'Não informado' }}
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Data:</strong><br>
                                                        {{ $message->created_at->format('d/m/Y H:i:s') }}
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>IP:</strong><br>
                                                        {{ $message->ip_address ?? 'Não registrado' }}
                                                    </div>
                                                    <div>
                                                        <strong>Mensagem:</strong><br>
                                                        <div class="bg-light p-3 rounded mt-2">
                                                            {{ $message->message }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <a href="mailto:{{ $message->email }}" class="btn btn-success">
                                                        <i class="fas fa-reply mr-2"></i>Responder por Email
                                                    </a>
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">Nenhuma mensagem encontrada</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $messages->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
