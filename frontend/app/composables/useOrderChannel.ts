type OrderEvent = { order?:Record<string,unknown>; measurement?:Record<string,unknown> }

export const useOrderChannel = () => {
  const api = useTailorsApi()
  const config = useRuntimeConfig()
  let echo: import('laravel-echo').default<'reverb'> | null = null
  let channelName = ''

  const leave = () => {
    if (echo && channelName) echo.leave(channelName)
    channelName = ''
  }

  const join = async (orderId:string, onEvent:(name:string,payload:OrderEvent)=>void) => {
    leave()
    if (!import.meta.client || !config.public.reverbKey || !api.token.value || !api.tenantId.value) return false
    const [{ default: Echo }, { default: Pusher }] = await Promise.all([import('laravel-echo'), import('pusher-js')])
    const apiOrigin = new URL(config.public.apiBase as string).origin
    echo ??= new Echo({
      broadcaster: 'reverb',
      key: config.public.reverbKey as string,
      wsHost: config.public.reverbHost as string,
      wsPort: Number(config.public.reverbPort),
      wssPort: Number(config.public.reverbPort),
      forceTLS: config.public.reverbScheme === 'https',
      enabledTransports: ['ws', 'wss'],
      client: new Pusher(config.public.reverbKey as string, { cluster: '', wsHost: config.public.reverbHost as string, wsPort: Number(config.public.reverbPort), wssPort: Number(config.public.reverbPort), forceTLS: config.public.reverbScheme === 'https', enabledTransports: ['ws', 'wss'] }),
      authEndpoint: `${apiOrigin}/broadcasting/auth`,
      auth: { headers: { 'X-Tenant': api.tenant.value } },
      authorizer: channel => ({
        authorize: async (socketId, callback) => {
          try {
            refreshCookie('XSRF-TOKEN')
            const xsrf = useCookie<string | null>('XSRF-TOKEN')
            const response = await $fetch<{ auth: string; channel_data?: string }>(`${apiOrigin}/broadcasting/auth`, {
              method: 'POST',
              credentials: 'include',
              headers: { Accept: 'application/json', 'X-Tenant': api.tenant.value, ...(xsrf.value ? { 'X-XSRF-TOKEN': xsrf.value } : {}) },
              body: { socket_id: socketId, channel_name: channel.name },
            })
            callback(null, response)
          } catch (error) { callback(error as Error, null) }
        },
      }),
    })
    channelName = `tenants.${api.tenantId.value}.orders.${orderId}`
    echo.private(channelName)
      .listen('.measurement.recorded', (payload:OrderEvent) => onEvent('measurement.recorded', payload))
      .listen('.order.ready', (payload:OrderEvent) => onEvent('order.ready', payload))
    return true
  }

  onBeforeUnmount(() => { leave(); echo?.disconnect(); echo = null })
  return { join, leave }
}
