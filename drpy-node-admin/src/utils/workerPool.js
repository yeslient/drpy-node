import workerpool from 'workerpool'

export const createWorkerPool = (workerUrl) => {
  let pool = null

  const getPool = () => {
    if (!pool) {
      pool = workerpool.pool(workerUrl, {
        maxWorkers: 1,
        workerOpts: { type: 'module' },
        workerType: 'web'
      })
    }
    return pool
  }

  return {
    exec(method, args) {
      return getPool().exec(method, args)
    },
    async terminate() {
      if (pool) {
        await pool.terminate()
        pool = null
      }
    }
  }
}
