import { aes, des, rabbit, rabbitLegacy, rc4, rc4Drop, rsa, sm4, tripleDes } from '@zy/crypto'
import workerpool from 'workerpool'

const METHOD_MAP = { rsa, rc4, rc4Drop, aes, des, tripleDes, rabbit, rabbitLegacy, sm4 }

const main = (action, execute, doc) => {
  const method = METHOD_MAP[action]?.[execute]
  return method(doc)
}

workerpool.worker({ main })
