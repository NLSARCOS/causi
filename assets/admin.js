jQuery(document).ready(function($) {
    let generationInProgress = false;
    let optimizationModal = null;
    let optimizationInterval = null;
    let currentFilter = 'all';
    
    // Toggle del sistema de caché
    $('#sbp-toggle-cache').on('change', function() {
        const checkbox = $(this);
        const isEnabled = checkbox.is(':checked');
        
        $.ajax({
            url: sbp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sbp_toggle_cache',
                nonce: sbp_ajax.nonce
            },
            beforeSend: function() {
                checkbox.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Actualizar estado visual
                    const statusText = $('.sbp-status-text');
                    if (response.data.enabled) {
                        statusText.text('🟢 Activo').removeClass('inactive').addClass('active');
                    } else {
                        statusText.text('🔴 Inactivo').removeClass('active').addClass('inactive');
                    }
                    
                    showNotification('✅ ' + response.data.message, 'success');
                } else {
                    // Revertir checkbox si hay error
                    checkbox.prop('checked', !isEnabled);
                    showNotification('❌ Error: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                // Revertir checkbox si hay error
                checkbox.prop('checked', !isEnabled);
                showNotification('❌ Error de conexión: ' + error, 'error');
                console.error('AJAX Error:', xhr.responseText);
            },
            complete: function() {
                checkbox.prop('disabled', false);
            }
        });
    });
    
    // Generar todas las páginas
    $('#sbp-generate-all').on('click', function() {
        if (generationInProgress) {
            return;
        }
        
        const button = $(this);
        const originalText = button.html();
        const progressContainer = $('#sbp-generation-progress');
        const progressBar = progressContainer.find('.sbp-progress-fill');
        const statusText = $('#sbp-generation-status');
        
        if (!confirm('¿Generar TODAS las páginas estáticas? Esto puede tomar varios minutos y usar recursos del servidor.')) {
            return;
        }
        
        generationInProgress = true;
        button.html('<span class="sbp-btn-icon">⏳</span> Generando...').prop('disabled', true);
        progressContainer.show();
        
        // Simular progreso
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += Math.random() * 2;
            if (progress > 95) progress = 95;
            
            progressBar.css('width', progress + '%');
            statusText.text(`Generando páginas... ${Math.round(progress)}%`);
        }, 2000);
        
        $.ajax({
            url: sbp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sbp_generate_all_pages',
                nonce: sbp_ajax.nonce
            },
            timeout: 600000, // 10 minutos
            success: function(response) {
                clearInterval(progressInterval);
                progressBar.css('width', '100%');
                
                if (response.success) {
                    statusText.text(`✅ ¡Completado! ${response.data.success} páginas generadas de ${response.data.total} total`);
                    showNotification(`✅ Generación exitosa: ${response.data.success}/${response.data.total} páginas`, 'success');
                    
                    if (response.data.errors > 0) {
                        showNotification(`⚠️ ${response.data.errors} páginas tuvieron errores`, 'warning');
                    }
                    
                    // Actualizar estadísticas
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    statusText.text('❌ Error en la generación');
                    showNotification('❌ Error: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                clearInterval(progressInterval);
                statusText.text('❌ Error de conexión o timeout');
                showNotification('❌ Error: ' + error, 'error');
                console.error('AJAX Error:', xhr.responseText);
            },
            complete: function() {
                generationInProgress = false;
                button.html(originalText).prop('disabled', false);
                
                setTimeout(() => {
                    progressContainer.hide();
                }, 5000);
            }
        });
    });
    
    // Precarga rápida
    $('#sbp-preload-cache').on('click', function() {
        const button = $(this);
        const originalText = button.html();
        
        button.html('<span class="sbp-btn-icon">⏳</span> Precargando...').prop('disabled', true);
        
        $.ajax({
            url: sbp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sbp_preload_cache',
                nonce: sbp_ajax.nonce
            },
            timeout: 120000, // 2 minutos
            success: function(response) {
                if (response.success) {
                    showNotification(`✅ Precarga completada: ${response.data.pages} páginas`, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showNotification('❌ Error: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('❌ Error de conexión: ' + error, 'error');
                console.error('AJAX Error:', xhr.responseText);
            },
            complete: function() {
                button.html(originalText).prop('disabled', false);
            }
        });
    });
    
    // NUEVA FUNCIONALIDAD: Optimizar assets con modal de seguimiento
    $('#sbp-optimize-assets').on('click', function() {
        openOptimizationModal();
    });
    
    // Limpiar caché
    $('#sbp-clear-cache').on('click', function() {
        const button = $(this);
        const originalText = button.html();
        
        if (!confirm('¿Limpiar todos los archivos estáticos? Esta acción no se puede deshacer.')) {
            return;
        }
        
        button.html('<span class="sbp-btn-icon">⏳</span> Limpiando...').prop('disabled', true);
        
        $.ajax({
            url: sbp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sbp_clear_cache',
                nonce: sbp_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('✅ Caché limpiado exitosamente', 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showNotification('❌ Error: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('❌ Error de conexión: ' + error, 'error');
                console.error('AJAX Error:', xhr.responseText);
            },
            complete: function() {
                button.html(originalText).prop('disabled', false);
            }
        });
    });
    
    // MODAL DE OPTIMIZACIÓN EN TIEMPO REAL
    function openOptimizationModal() {
        optimizationModal = $('#sbp-optimization-modal');
        optimizationModal.show();
        
        // Inicializar estado
        updateModalStatus('⏳', 'Iniciando optimización...', 0);
        showModalControls(['stop']);
        
        // Iniciar optimización
        startOptimization();
        
        // Iniciar polling de estado
        startStatusPolling();
    }
    
    function closeOptimizationModal() {
        if (optimizationModal) {
            optimizationModal.hide();
            stopStatusPolling();
        }
    }
    
    function startOptimization() {
        $.ajax({
            url: sbp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sbp_start_optimization',
                nonce: sbp_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateModalStatus('🚀', 'Optimización iniciada...', 0);
                    showModalControls(['pause', 'stop']);
                    
                    // Mostrar páginas iniciales
                    updatePagesList(response.data.urls.map(url => ({
                        url: url,
                        status: 'pending',
                        time: new Date().toLocaleTimeString()
                    })));
                } else {
                    updateModalStatus('❌', 'Error al iniciar: ' + response.data, 0);
                    showModalControls(['stop']);
                }
            },
            error: function() {
                updateModalStatus('❌', 'Error de conexión', 0);
                showModalControls(['stop']);
            }
        });
    }
    
    function startStatusPolling() {
        optimizationInterval = setInterval(function() {
            $.ajax({
                url: sbp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sbp_get_optimization_status',
                    nonce: sbp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        const progress = Math.round((data.current_page / data.total_pages) * 100);
                        
                        // Actualizar estado según el status
                        switch (data.status) {
                            case 'running':
                                updateModalStatus('🚀', `Optimizando página ${data.current_page} de ${data.total_pages}...`, progress);
                                showModalControls(['pause', 'stop']);
                                break;
                            case 'paused':
                                updateModalStatus('⏸️', 'Optimización pausada', progress);
                                showModalControls(['resume', 'stop']);
                                break;
                            case 'completed':
                                updateModalStatus('✅', 'Optimización completada', 100);
                                showModalControls([]);
                                stopStatusPolling();
                                showNotification('✅ Optimización de assets completada', 'success');
                                break;
                            case 'stopped':
                                updateModalStatus('⏹️', 'Optimización detenida', progress);
                                showModalControls([]);
                                stopStatusPolling();
                                break;
                        }
                        
                        // Actualizar estadísticas
                        updateRealTimeStats(data.stats);
                        
                        // Actualizar lista de páginas
                        if (data.recent_results) {
                            updatePagesList(data.recent_results);
                        }
                    }
                }
            });
        }, 2000); // Cada 2 segundos
    }
    
    function stopStatusPolling() {
        if (optimizationInterval) {
            clearInterval(optimizationInterval);
            optimizationInterval = null;
        }
    }
    
    function updateModalStatus(icon, text, progress) {
        $('#sbp-status-icon').text(icon);
        $('#sbp-status-text').text(text);
        $('#sbp-modal-progress-fill').css('width', progress + '%');
        $('#sbp-progress-percentage').text(progress + '%');
    }
    
    function showModalControls(controls) {
        // Ocultar todos los controles
        $('#sbp-pause-btn, #sbp-resume-btn, #sbp-stop-btn').hide();
        
        // Mostrar controles específicos
        controls.forEach(function(control) {
            $('#sbp-' + control + '-btn').show();
        });
    }
    
    function updateRealTimeStats(stats) {
        $('#sbp-stat-optimized').text(stats.optimized || 0);
        $('#sbp-stat-processing').text(stats.processing || 0);
        $('#sbp-stat-pending').text(stats.pending || 0);
        $('#sbp-stat-errors').text(stats.errors || 0);
    }
    
    function updatePagesList(pages) {
        const pagesList = $('#sbp-pages-list');
        
        // Limpiar lista actual
        pagesList.empty();
        
        // Añadir páginas
        pages.forEach(function(page) {
            const statusIcon = getStatusIcon(page.status);
            const pageItem = $(`
                <div class="sbp-page-item" data-status="${page.status}">
                    <span class="sbp-page-status">${statusIcon}</span>
                    <span class="sbp-page-url">${page.url}</span>
                    <span class="sbp-page-time">${page.time}</span>
                </div>
            `);
            
            pagesList.append(pageItem);
        });
        
        // Aplicar filtro actual
        applyPageFilter(currentFilter);
    }
    
    function getStatusIcon(status) {
        switch (status) {
            case 'success':
            case 'optimized':
                return '✅';
            case 'processing':
            case 'optimizing':
                return '⏳';
            case 'pending':
                return '⏸️';
            case 'error':
                return '❌';
            default:
                return '⏸️';
        }
    }
    
    function applyPageFilter(filter) {
        const pageItems = $('.sbp-page-item');
        
        if (filter === 'all') {
            pageItems.show();
        } else {
            pageItems.hide();
            pageItems.filter(`[data-status="${filter}"]`).show();
        }
    }
    
    // Event listeners para controles del modal
    $('#sbp-pause-btn').on('click', function() {
        $.ajax({
            url: sbp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sbp_pause_optimization',
                nonce: sbp_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('⏸️ Optimización pausada', 'info');
                }
            }
        });
    });
    
    $('#sbp-resume-btn').on('click', function() {
        $.ajax({
            url: sbp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sbp_resume_optimization',
                nonce: sbp_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('▶️ Optimización reanudada', 'success');
                }
            }
        });
    });
    
    $('#sbp-stop-btn').on('click', function() {
        if (confirm('¿Detener la optimización? El progreso actual se perderá.')) {
            $.ajax({
                url: sbp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sbp_stop_optimization',
                    nonce: sbp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('⏹️ Optimización detenida', 'warning');
                        closeOptimizationModal();
                    }
                }
            });
        }
    });
    
    // Cerrar modal
    $('.sbp-modal-close').on('click', function() {
        closeOptimizationModal();
    });
    
    // Cerrar modal al hacer clic fuera
    $(window).on('click', function(event) {
        if (event.target.id === 'sbp-optimization-modal') {
            closeOptimizationModal();
        }
    });
    
    // Filtros de páginas
    $('.sbp-filter-btn').on('click', function() {
        const filter = $(this).data('filter');
        currentFilter = filter;
        
        // Actualizar botones activos
        $('.sbp-filter-btn').removeClass('active');
        $(this).addClass('active');
        
        // Aplicar filtro
        applyPageFilter(filter);
    });
    
    // Configuración colapsable
    $('.sbp-collapsible').on('click', function() {
        const target = $(this).data('target');
        const content = $('#' + target);
        const arrow = $(this).find('.sbp-arrow');
        
        content.slideToggle();
        arrow.toggleClass('rotated');
    });
    
    // Función para mostrar notificaciones
    function showNotification(message, type = 'info') {
        const notification = $(`
            <div class="sbp-notification sbp-notification-${type}">
                ${message}
                <button class="sbp-notification-close">&times;</button>
            </div>
        `);
        
        // Agregar estilos si no existen
        if (!$('#sbp-notification-styles').length) {
            $('head').append(`
                <style id="sbp-notification-styles">
                .sbp-notification {
                    position: fixed;
                    top: 32px;
                    right: 20px;
                    padding: 12px 20px;
                    border-radius: 6px;
                    color: white;
                    font-weight: 500;
                    z-index: 999999;
                    max-width: 400px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    animation: sbpSlideIn 0.3s ease-out;
                }
                
                .sbp-notification-success { background: #00a32a; }
                .sbp-notification-error { background: #d63638; }
                .sbp-notification-warning { background: #f56e28; }
                .sbp-notification-info { background: #0073aa; }
                
                .sbp-notification-close {
                    background: none;
                    border: none;
                    color: white;
                    font-size: 18px;
                    margin-left: 10px;
                    cursor: pointer;
                    padding: 0;
                }
                
                @keyframes sbpSlideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                </style>
            `);
        }
        
        $('body').append(notification);
        
        notification.find('.sbp-notification-close').on('click', function() {
            notification.fadeOut(300, function() { $(this).remove(); });
        });
        
        setTimeout(() => {
            if (notification.is(':visible')) {
                notification.fadeOut(300, function() { $(this).remove(); });
            }
        }, 7000);
    }
    
    // Auto-refresh de estadísticas cada 30 segundos
    setInterval(function() {
        if (!generationInProgress && !optimizationInterval) {
            $.ajax({
                url: sbp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sbp_get_stats',
                    nonce: sbp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Actualizar estadísticas en tiempo real
                        const stats = response.data;
                        $('.sbp-stat-number').eq(0).text(stats.files);
                        $('.sbp-stat-number').eq(3).text(formatBytes(stats.size));
                    }
                },
                error: function() {
                    // Silencioso - no mostrar errores para auto-refresh
                }
            });
        }
    }, 30000);
    
    // Función auxiliar para formatear bytes
    function formatBytes(bytes, precision = 1) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        
        while (bytes > 1024 && i < units.length - 1) {
            bytes /= 1024;
            i++;
        }
        
        return Math.round(bytes * Math.pow(10, precision)) / Math.pow(10, precision) + ' ' + units[i];
    }
});

// Función global para la barra de administración
function sbpClearCache() {
    if (confirm('¿Limpiar todos los archivos estáticos?')) {
        jQuery.ajax({
            url: sbp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sbp_clear_cache',
                nonce: sbp_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Caché limpiado exitosamente');
                    location.reload();
                } else {
                    alert('❌ Error: ' + response.data);
                }
            },
            error: function() {
                alert('❌ Error de conexión');
            }
        });
    }
}