const express = require('express');

/**
 * Starts a mock event source server.
 *
 * @param {Object} options
 * @param {string} options.sourceName - Name to include in payload.
 * @param {number} options.port - Port to run the server on.
 * @param {number} [options.eventCount=3] - Number of events to return per request.
 */
function startServer({ sourceName, port, eventCount = 3 }) {
    const app = express();

    app.get('/events', (req, res) => {
        // Randomly throw error with 20% probability
        if (Math.random() < 0.2) {
            // simulate server error
            return res.status(500).json({ error: 'Random server error occurred' });
        }

        const lastId = parseInt(req.query.lastId || 0, 10);
        const events = Array.from({ length: eventCount }, (_, i) => ({
            id: lastId + i + 1,
            payload: `${sourceName} - Event ${lastId + i + 1}`,
        }));
        res.json({ events });
    });

    app.listen(port, () => {
        console.log(`${sourceName} running at http://localhost:${port}/events`);
    });
}

module.exports = startServer;
